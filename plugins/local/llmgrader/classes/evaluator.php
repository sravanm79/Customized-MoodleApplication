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

namespace local_llmgrader;

use local_llmgrader\provider\factory;
use local_llmgrader\provider\provider;

/**
 * Sends a grading conversation to the configured provider and turns the reply into a checked result.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evaluator {
    /** @var provider */
    protected $provider;

    /**
     * @param provider|null $provider Defaults to the configured one.
     */
    public function __construct(?provider $provider = null) {
        $this->provider = $provider ?? factory::get();
    }

    /**
     * Grades.
     *
     * @param array $messages From prompt_builder::build().
     * @return array ['score' => awarded marks, 'maxscore' => total marks, 'tasks' => [[task, out_of, awarded, comment]],
     *     'feedback' => string, 'metadata' => [model, prompttokens, completiontokens, latency]]
     * @throws \moodle_exception Transient: retry. permanent_failure: give up.
     */
    public function evaluate(array $messages): array {
        $start = microtime(true);
        $response = $this->provider->complete($messages, ['json' => true, 'temperature' => 0.1, 'max_tokens' => 1500]);
        $latency = round(microtime(true) - $start, 1);

        // Some models wrap the JSON in a ``` fence despite the JSON response format.
        $content = preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/', '', $response['content']);
        $result = json_decode($content, true);
        if (!is_array($result) || empty($result['tasks']) || !is_array($result['tasks'])) {
            // Transient: a retry usually produces valid JSON.
            throw new \moodle_exception('invalidresponse', 'local_llmgrader', '', \core_text::substr($content, 0, 300));
        }

        // Compute the total here; never trust a total from the model, and keep each criterion within its marks.
        $tasks = [];
        $score = 0.0;
        $maxscore = 0.0;
        foreach ($result['tasks'] as $t) {
            $outof = max(0.0, (float) ($t['out_of'] ?? 0));
            $awarded = min(max(0.0, (float) ($t['awarded'] ?? 0)), $outof);
            $tasks[] = [
                'task' => clean_param($t['task'] ?? '', PARAM_TEXT),
                'out_of' => $outof,
                'awarded' => $awarded,
                'comment' => clean_param($t['comment'] ?? '', PARAM_TEXT),
            ];
            $score += $awarded;
            $maxscore += $outof;
        }
        if ($maxscore <= 0) {
            throw new \moodle_exception('invalidresponse', 'local_llmgrader', '', 'No criterion marks in the reply.');
        }
        return [
            'score' => $score,
            'maxscore' => $maxscore,
            'tasks' => $tasks,
            'feedback' => clean_param($result['feedback'] ?? '', PARAM_TEXT),
            'metadata' => [
                'model' => $response['model'],
                'prompttokens' => $response['usage']['prompt_tokens'] ?? null,
                'completiontokens' => $response['usage']['completion_tokens'] ?? null,
                'latency' => $latency,
            ],
        ];
    }
}
