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
 * Offline provider for testing the pipeline without an LLM: returns a well-formed grading reply built from the
 * rubric lines in the prompt ("- Criterion name [N marks]"), awarding a fixed share of each, deterministically.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mock implements provider {
    /**
     * Name for the settings page.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('provider_mock', 'local_llmgrader');
    }

    /**
     * Builds a reply from the prompt.
     *
     * @param array $messages
     * @param array $options
     * @return array
     */
    public function complete(array $messages, array $options = []): array {
        $prompt = implode("\n", array_column($messages, 'content'));
        preg_match_all('/^\s*[-*]\s*(.+?)\s*\[(\d+(?:\.\d+)?)\s*marks?\]/mi', $prompt, $matches, PREG_SET_ORDER);
        if (!$matches) {
            $matches = [[null, 'Overall quality', '10']];
        }
        $tasks = [];
        foreach ($matches as $i => [, $name, $marks]) {
            $outof = (float) $marks;
            $tasks[] = ['task' => $name, 'out_of' => $outof, 'awarded' => round($outof * ($i % 2 ? 0.5 : 1), 1),
                'comment' => $i % 2 ? 'Partly correct; check the edge cases.' : 'Correct and clearly written.'];
        }
        return [
            'content' => json_encode(['tasks' => $tasks,
                'feedback' => 'Good work overall. (Mock provider: this is test output, not a real evaluation.)']),
            'model' => 'mock',
            'usage' => ['prompt_tokens' => (int) (strlen($prompt) / 4), 'completion_tokens' => 120],
        ];
    }
}
