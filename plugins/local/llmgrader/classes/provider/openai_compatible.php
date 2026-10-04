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

namespace local_llmgrader\provider;

use local_llmgrader\permanent_failure;

/**
 * OpenAI-compatible chat completions endpoint (vLLM, OpenAI, Azure OpenAI, Ollama, LiteLLM, ...), as one request or
 * streamed (server-sent events), per the "Stream responses" setting.
 *
 * Uses PHP curl directly: Moodle's \curl wrapper only allows ports 80/443 by default, and vLLM often runs elsewhere.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openai_compatible implements provider {
    /** @var \stdClass Plugin settings. */
    protected $config;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->config = get_config('local_llmgrader');
    }

    /**
     * Name for the settings page.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('provider_openai_compatible', 'local_llmgrader');
    }

    /**
     * Sends the conversation.
     *
     * @param array $messages
     * @param array $options
     * @return array
     */
    public function complete(array $messages, array $options = []): array {
        $stream = !empty($this->config->stream);
        $payload = [
            'model' => $this->config->model,
            'temperature' => $options['temperature'] ?? 0.1,
            'max_tokens' => $options['max_tokens'] ?? 1500,
            'messages' => $messages,
        ];
        if (!empty($options['json'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        if ($stream) {
            $payload['stream'] = true;
            $payload['stream_options'] = ['include_usage' => true];
        }
        return $stream ? $this->post_stream($payload) : $this->post($payload);
    }

    /**
     * One request, one JSON response.
     *
     * @param array $payload
     * @return array
     */
    protected function post(array $payload): array {
        [$raw, $status] = $this->request($payload, null);
        $data = json_decode($raw, true);
        $this->check_status($status, $raw, is_array($data));
        return [
            'content' => (string) ($data['choices'][0]['message']['content'] ?? ''),
            'model' => $data['model'] ?? $payload['model'],
            'usage' => [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? null,
            ],
        ];
    }

    /**
     * Streamed request: the reply arrives as "data: {json}" lines, each with a piece of the content.
     *
     * @param array $payload
     * @return array
     */
    protected function post_stream(array $payload): array {
        $content = '';
        $model = $payload['model'];
        $usage = ['prompt_tokens' => null, 'completion_tokens' => null];
        $buffer = '';
        $raw = '';
        $onchunk = function($ch, string $chunk) use (&$content, &$model, &$usage, &$buffer, &$raw): int {
            $raw .= $chunk;
            $buffer .= $chunk;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if (strpos($line, 'data:') !== 0 || ($line = trim(substr($line, 5))) === '[DONE]') {
                    continue;
                }
                $event = json_decode($line, true);
                if (!is_array($event)) {
                    continue;
                }
                $content .= $event['choices'][0]['delta']['content'] ?? '';
                $model = $event['model'] ?? $model;
                if (!empty($event['usage'])) {
                    $usage = ['prompt_tokens' => $event['usage']['prompt_tokens'] ?? null,
                        'completion_tokens' => $event['usage']['completion_tokens'] ?? null];
                }
            }
            return strlen($chunk);
        };
        [, $status] = $this->request($payload, $onchunk);
        // Errors come back as a plain JSON body, not as events.
        $this->check_status($status, $raw, $status === 200);
        return ['content' => $content, 'model' => $model, 'usage' => $usage];
    }

    /**
     * Sends the request.
     *
     * @param array $payload
     * @param callable|null $onchunk Receives the body as it arrives (streaming).
     * @return array [body (empty when streamed), HTTP status]
     */
    protected function request(array $payload, ?callable $onchunk): array {
        $headers = ['Content-Type: application/json'];
        if (!empty($this->config->apikey)) {
            $headers[] = 'Authorization: Bearer ' . $this->config->apikey;
        }
        $ch = curl_init(rtrim($this->config->llmurl, '/') . '/chat/completions');
        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => $onchunk === null,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => (int) ($this->config->timeout ?: 180),
        ];
        if ($onchunk) {
            $options[CURLOPT_WRITEFUNCTION] = $onchunk;
        }
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \moodle_exception('llmunreachable', 'local_llmgrader', '', $error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$onchunk ? '' : (string) $raw, $status];
    }

    /**
     * Turns HTTP errors into retryable or permanent exceptions.
     *
     * @param int $status
     * @param string $raw
     * @param bool $ok The body was usable.
     */
    protected function check_status(int $status, string $raw, bool $ok): void {
        if ($status >= 500 || $status === 429) {
            throw new \moodle_exception('llmhttperror', 'local_llmgrader', '', "HTTP $status");
        }
        if ($status !== 200 || !$ok) {
            // 4xx: the request itself is wrong (model name, prompt too long); retrying won't help.
            throw new permanent_failure("LLM returned HTTP $status: " . \core_text::substr($raw, 0, 300));
        }
    }
}
