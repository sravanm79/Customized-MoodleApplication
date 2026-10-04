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

namespace theme_iiitdwd\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Turns the markdown cells and HTML outputs of a Jupyter notebook into safe HTML for the notebook viewer
 * (amd/src/ipynb_viewer.js).
 *
 * Notebooks are untrusted (student submissions are shown to teachers), so their markdown and HTML go through
 * Moodle's Markdown converter and HTML Purifier (clean_text) on the server: scripts, event handlers and the
 * like are removed before the browser sees them. Code and plain-text outputs never come here; the viewer escapes them.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sanitise_notebook_html extends external_api {
    /** @var int Maximum total size of the text in one call, in bytes. */
    const MAX_BYTES = 8 * 1024 * 1024;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'items' => new external_multiple_structure(new external_single_structure([
                'format' => new external_value(PARAM_ALPHA, 'markdown or html'),
                'text' => new external_value(PARAM_RAW, 'Cell source or output'),
            ])),
        ]);
    }

    /**
     * Converts and cleans each item.
     *
     * @param array $items
     * @return string[] Safe HTML, one per item, in order.
     */
    public static function execute(array $items): array {
        ['items' => $items] = self::validate_parameters(self::execute_parameters(), ['items' => $items]);
        self::validate_context(\context_system::instance());

        if (array_sum(array_map(fn($item) => strlen($item['text']), $items)) > self::MAX_BYTES) {
            throw new \moodle_exception('ipynbtoolarge', 'theme_iiitdwd');
        }
        return array_map(function(array $item): string {
            $html = $item['format'] === 'markdown' ? markdown_to_html(self::commonmark_lists($item['text'])) : $item['text'];
            return clean_text($html, FORMAT_HTML);
        }, $items);
    }

    /**
     * Let lists start straight after a line of text, as they do in Jupyter (CommonMark).
     *
     * Moodle's Markdown (classic Markdown) only starts a list after a blank line, so "Some text:\n- one\n- two" would
     * render as one paragraph. Inserts that blank line before a list item that follows ordinary text, leaving fenced
     * code blocks, tight lists and indented continuation lines alone.
     *
     * @param string $markdown
     * @return string
     */
    public static function commonmark_lists(string $markdown): string {
        $lines = preg_split('/\r\n|\r|\n/', $markdown);
        $listitem = '/^ {0,3}(?:[-*+]|\d{1,9}[.)])[ \t]+\S/';
        $out = [];
        $infence = false;
        $previous = '';
        foreach ($lines as $line) {
            if (preg_match('/^ {0,3}(```|~~~)/', $line)) {
                $infence = !$infence;
            } else if (!$infence && preg_match($listitem, $line) && trim($previous) !== ''
                    && !preg_match($listitem, $previous) && !preg_match('/^(\s{2,}|\t)/', $previous)
                    && !preg_match('/^ {0,3}(#|>|\||```|~~~)/', $previous)) {
                $out[] = '';
            }
            $out[] = $line;
            $previous = $line;
        }
        return implode("\n", $out);
    }

    /**
     * Return structure.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_value(PARAM_RAW, 'Safe HTML'));
    }
}
