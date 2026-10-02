<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_jupyter;

/**
 * Thin client for the JupyterHub REST API and the single-user Contents API.
 *
 * All calls use the hub's "moodle" service token and go to the internal hub URL
 * (container-to-container); only the iframe URL uses the public hub URL.
 *
 * @package   mod_jupyter
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hub_client {

    /** @var int Seconds to wait for a user server to become ready. */
    const START_TIMEOUT = 90;

    /** @var string Hub URL reachable from the Moodle server. */
    private string $internalurl;

    /** @var string Hub URL reachable from the user's browser. */
    private string $publicurl;

    /** @var string Service API token. */
    private string $token;

    /**
     * Load the hub connection from the plugin settings.
     */
    public function __construct() {
        $config = get_config('mod_jupyter');
        $this->internalurl = rtrim($config->hubinternalurl ?? '', '/');
        $this->publicurl = rtrim($config->hubpublicurl ?? '', '/');
        $this->token = $config->hubtoken ?? '';
        if ($this->internalurl === '' || $this->publicurl === '' || $this->token === '') {
            throw new \moodle_exception('hubnotconfigured', 'mod_jupyter');
        }
    }

    /**
     * Hub username for a Moodle user.
     *
     * @param int $userid
     * @return string
     */
    public static function username(int $userid): string {
        return 'moodle' . $userid;
    }

    /**
     * Make sure the hub user exists and their server is running.
     *
     * @param string $user Hub username.
     */
    public function ensure_server(string $user): void {
        [$status, $body] = $this->request('POST', '/hub/api/users/' . $user);
        if ($status !== 201 && $status !== 409) {
            $this->fail('create user', $status, $body);
        }

        $model = $this->get_user($user);
        if (!empty($model['servers']['']['ready'])) {
            return;
        }
        if (empty($model['servers'][''])) {
            [$status, $body] = $this->request('POST', '/hub/api/users/' . $user . '/server');
            // 400 means a start is already in progress.
            if (!in_array($status, [201, 202, 400])) {
                $this->fail('start server', $status, $body);
            }
        }

        $deadline = time() + self::START_TIMEOUT;
        while (time() < $deadline) {
            $model = $this->get_user($user);
            if (!empty($model['servers']['']['ready'])) {
                return;
            }
            sleep(1);
        }
        throw new \moodle_exception('hubtimeout', 'mod_jupyter');
    }

    /**
     * Create the notebook in the user's server if it is not there yet.
     *
     * @param string $user Hub username.
     * @param string $path Notebook path relative to the user's home.
     * @param string $json Notebook JSON to use when creating it.
     */
    public function ensure_notebook(string $user, string $path, string $json): void {
        [$status] = $this->request('GET', $this->contents_path($user, $path) . '?content=0');
        if ($status === 200) {
            return;
        }
        $this->put_notebook($user, $path, $json);
    }

    /**
     * Write (or overwrite) a notebook in the user's server.
     *
     * @param string $user Hub username.
     * @param string $path Notebook path relative to the user's home.
     * @param string $json Notebook JSON.
     */
    public function put_notebook(string $user, string $path, string $json): void {
        $dir = dirname($path);
        if ($dir !== '.' && $dir !== '') {
            [$status] = $this->request('GET', $this->contents_path($user, $dir) . '?content=0');
            if ($status === 404) {
                [$status, $body] = $this->request('PUT', $this->contents_path($user, $dir), ['type' => 'directory']);
                if ($status !== 201 && $status !== 200) {
                    $this->fail('create folder', $status, $body);
                }
            }
        }

        // Decode to objects (not arrays) so empty {} metadata survives re-encoding.
        $content = json_decode($json);
        if (!is_object($content)) {
            throw new \moodle_exception('invalidnotebook', 'mod_jupyter');
        }
        [$status, $body] = $this->request('PUT', $this->contents_path($user, $path),
            ['type' => 'notebook', 'format' => 'json', 'content' => $content]);
        if ($status !== 201 && $status !== 200) {
            $this->fail('write notebook', $status, $body);
        }
    }

    /**
     * Read a notebook from the user's server.
     *
     * @param string $user Hub username.
     * @param string $path Notebook path relative to the user's home.
     * @return string Notebook JSON.
     */
    public function fetch_notebook(string $user, string $path): string {
        [$status, $body] = $this->request('GET', $this->contents_path($user, $path) . '?content=1&type=notebook');
        if ($status !== 200) {
            $this->fail('read notebook', $status, $body);
        }
        $model = json_decode($body);
        return json_encode($model->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Browser URL that opens the notebook in JupyterLab, logged in as the user.
     *
     * @param string $user Hub username.
     * @param string $path Notebook path relative to the user's home.
     * @return string
     */
    public function notebook_url(string $user, string $path): string {
        [$status, $body] = $this->request('POST', '/hub/api/users/' . $user . '/tokens',
            ['expires_in' => 8 * HOURSECS, 'note' => 'Moodle launch']);
        $data = json_decode($body, true);
        if ($status !== 201 || empty($data['token'])) {
            $this->fail('create token', $status, $body);
        }
        return $this->publicurl . '/user/' . $user . '/lab/tree/' . self::encode_path($path)
            . '?token=' . urlencode($data['token']);
    }

    /**
     * Fetch the hub user model.
     *
     * @param string $user
     * @return array
     */
    private function get_user(string $user): array {
        [$status, $body] = $this->request('GET', '/hub/api/users/' . $user);
        if ($status !== 200) {
            $this->fail('get user', $status, $body);
        }
        return json_decode($body, true);
    }

    /**
     * Contents API path for a file in the user's server.
     *
     * @param string $user
     * @param string $path
     * @return string
     */
    private function contents_path(string $user, string $path): string {
        return '/user/' . $user . '/api/contents/' . self::encode_path($path);
    }

    /**
     * URL-encode each segment of a relative path.
     *
     * @param string $path
     * @return string
     */
    private static function encode_path(string $path): string {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * Send a request to the hub.
     *
     * Uses PHP curl directly: Moodle's \curl wrapper blocks private Docker
     * addresses and non-standard ports by default.
     *
     * @param string $method
     * @param string $path Path on the hub, starting with '/'.
     * @param array|null $payload JSON body.
     * @return array [HTTP status, raw response body]
     */
    private function request(string $method, string $path, ?array $payload = null): array {
        $headers = ['Authorization: token ' . $this->token, 'Accept: application/json'];
        $ch = curl_init($this->internalurl . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \moodle_exception('hubunreachable', 'mod_jupyter', '', $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, $body];
    }

    /**
     * Throw an exception describing a failed hub call.
     *
     * @param string $action
     * @param int $status
     * @param string $body
     */
    private function fail(string $action, int $status, string $body): void {
        $data = json_decode($body, true);
        throw new \moodle_exception('huberror', 'mod_jupyter', '', (object) [
            'action' => $action,
            'status' => $status,
            'message' => is_array($data) ? ($data['message'] ?? '') : '',
        ]);
    }
}
