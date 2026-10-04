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

/**
 * An LLM backend. Implementations live in this namespace and are picked in the plugin settings
 * (local_llmgrader/provider = the class's short name); see \local_llmgrader\provider\factory.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface provider {
    /**
     * Human-readable name for the settings page.
     *
     * @return string
     */
    public static function get_name(): string;

    /**
     * Sends a chat conversation and returns the model's reply.
     *
     * Throw \local_llmgrader\permanent_failure for errors a retry cannot fix (bad request, unknown model) and
     * \moodle_exception for transient ones (timeouts, 5xx, rate limits); the task retries those with backoff.
     *
     * @param array $messages [['role' => 'system'|'user', 'content' => string], ...]
     * @param array $options 'json' (bool, ask for a JSON object), 'max_tokens', 'temperature'
     * @return array ['content' => string, 'model' => string, 'usage' => ['prompt_tokens' => ?int, 'completion_tokens' => ?int]]
     */
    public function complete(array $messages, array $options = []): array;
}
