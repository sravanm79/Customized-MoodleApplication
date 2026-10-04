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

/**
 * Jupyter notebooks as compact text for the prompt: instructions, student code and outputs; boilerplate check cells
 * reduced to their output; images dropped.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notebook {

    /**
     * Turn a notebook into compact text: instructions, student code and outputs. Drops boilerplate and images.
     *
     * @param string $json Raw .ipynb content
     * @param int $maxchars
     * @return string
     */
    public static function condense(string $json, int $maxchars): string {
        $nb = json_decode($json, true);
        if (!is_array($nb) || !isset($nb['cells']) || !is_array($nb['cells'])) {
            throw new permanent_failure('The submitted file is not a valid Jupyter notebook.');
        }
        $parts = [];
        foreach ($nb['cells'] as $i => $cell) {
            $src = trim(self::join($cell['source'] ?? ''));
            $type = $cell['cell_type'] ?? '';
            if ($type === 'markdown') {
                if ($src !== '') {
                    $parts[] = "[Cell $i | instructions]\n" . \core_text::substr($src, 0, 700);
                }
                continue;
            }
            if ($type !== 'code') {
                continue;
            }
            $outs = [];
            foreach ($cell['outputs'] ?? [] as $o) {
                if (($o['output_type'] ?? '') === 'error') {
                    $outs[] = 'ERROR ' . ($o['ename'] ?? '') . ': ' . ($o['evalue'] ?? '');
                } else if (isset($o['text'])) {
                    $outs[] = self::join($o['text']);
                } else if (isset($o['data'])) {
                    $outs[] = isset($o['data']['text/plain']) ? self::join($o['data']['text/plain']) : '[image/other output]';
                }
            }
            $out = trim(implode('', $outs));
            // Provided setup/check cells ("... Do not edit.") are boilerplate: keep only their output.
            $firstline = strtok($src, "\n");
            if ($firstline !== false && strpos($firstline, 'Do not edit.') !== false) {
                if ($out !== '') {
                    $parts[] = "[Cell $i | provided check, output]\n" . \core_text::substr($out, 0, 400);
                }
                continue;
            }
            $parts[] = "[Cell $i | student code]\n" . \core_text::substr($src, 0, 1500)
                . "\n[output]\n" . ($out !== '' ? \core_text::substr($out, 0, 600) : '(not run / no output)');
        }
        return \core_text::substr(implode("\n\n", $parts), 0, $maxchars);
    }

    /**
     * Notebook "source"/"text" fields are either a string or a list of lines.
     *
     * @param mixed $value
     * @return string
     */
    protected static function join($value): string {
        return is_array($value) ? implode('', $value) : (string) $value;
    }
}
