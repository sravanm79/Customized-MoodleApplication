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
 * The gradable content of a submission, as text for the prompt: online text, Jupyter notebooks (condensed to
 * instructions, code and outputs) and source/text files. Other files (PDF, images, Word) are listed but not sent.
 *
 * @package   local_llmgrader
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_content {
    /** @var string[] File extensions read as plain text. */
    const TEXT_EXTENSIONS = ['py', 'java', 'c', 'h', 'cpp', 'hpp', 'cc', 'cs', 'js', 'ts', 'go', 'rs', 'rb', 'php', 'kt',
        'swift', 'scala', 'r', 'm', 'jl', 'sql', 'sh', 'html', 'css', 'xml', 'json', 'yaml', 'yml', 'md', 'txt', 'csv',
        'tex'];

    /** @var int Largest single file read, in bytes. */
    const MAX_FILE_BYTES = 512 * 1024;

    /**
     * Collects the content.
     *
     * @param \context_module $context The assignment's context.
     * @param \stdClass $submission assign_submission record
     * @param int $maxchars Cut the text at this length (the model's context).
     * @return array|null ['text' => string, 'hash' => sha1 of the content, 'fileid' => first file id or 0,
     *     'filename' => names, comma-separated], or null if there is nothing gradable.
     */
    public static function extract(\context_module $context, \stdClass $submission, int $maxchars): ?array {
        global $DB;
        $parts = [];
        $names = [];
        $fileid = 0;

        $onlinetext = $DB->get_field('assignsubmission_onlinetext', 'onlinetext', ['submission' => $submission->id]);
        if ($onlinetext !== false && trim(strip_tags($onlinetext)) !== '') {
            $parts[] = "### Online text\n" . trim(html_to_text($onlinetext, 0, false));
            $names[] = get_string('onlinetext', 'local_llmgrader');
        }

        $files = get_file_storage()->get_area_files($context->id, 'assignsubmission_file', 'submission_files',
            $submission->id, 'filepath, filename', false);
        foreach ($files as $file) {
            $name = $file->get_filepath() === '/' ? $file->get_filename() : ltrim($file->get_filepath(), '/') . $file->get_filename();
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($extension === 'ipynb') {
                $text = notebook::condense($file->get_content(), $maxchars);
            } else if (in_array($extension, self::TEXT_EXTENSIONS, true) && $file->get_filesize() <= self::MAX_FILE_BYTES) {
                $text = $file->get_content();
                if (!mb_check_encoding($text, 'UTF-8')) {
                    $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
                }
                $text = "```$extension\n" . rtrim($text) . "\n```";
            } else {
                $parts[] = "### File: $name\n(" . get_string('filenotread', 'local_llmgrader') . ')';
                continue;
            }
            $parts[] = "### File: $name\n" . $text;
            $names[] = $name;
            $fileid = $fileid ?: (int) $file->get_id();
        }

        if (!$names) {
            return null;
        }
        $text = implode("\n\n", $parts);
        return [
            'text' => \core_text::substr($text, 0, $maxchars),
            'hash' => sha1($text),
            'fileid' => $fileid,
            'filename' => \core_text::substr(implode(', ', $names), 0, 255),
        ];
    }
}
