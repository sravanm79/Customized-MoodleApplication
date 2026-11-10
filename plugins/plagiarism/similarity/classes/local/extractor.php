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

namespace plagiarism_similarity\local;

/**
 * The text of an assignment submission that is compared: online text, source and text files, notebook cells
 * (code and markdown, not outputs) and Word / OpenDocument documents.
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extractor {

    /** File extensions read as plain text. */
    const TEXT_EXTENSIONS = ['py', 'java', 'c', 'h', 'cpp', 'hpp', 'cc', 'cs', 'js', 'ts', 'go', 'rs', 'rb', 'php', 'kt',
        'swift', 'scala', 'r', 'm', 'jl', 'sql', 'sh', 'html', 'htm', 'css', 'xml', 'json', 'yaml', 'yml', 'md', 'txt',
        'csv', 'tex', 'rtf'];

    /** Largest file read, in bytes. */
    const MAX_FILE_BYTES = 5 * 1024 * 1024;

    /** Most text kept per submission, in characters. */
    const MAX_CHARS = 300000;

    /**
     * Text of a submission.
     *
     * @param \context_module $context
     * @param \stdClass $submission assign_submission record
     * @return array ['text' => string, 'skipped' => string[] names of files that could not be read]
     */
    public static function extract(\context_module $context, \stdClass $submission): array {
        global $DB;
        $parts = [];
        $skipped = [];

        $onlinetext = $DB->get_field('assignsubmission_onlinetext', 'onlinetext', ['submission' => $submission->id]);
        if ($onlinetext !== false && trim(strip_tags($onlinetext)) !== '') {
            $parts[] = html_to_text($onlinetext, 0, false);
        }

        $files = get_file_storage()->get_area_files($context->id, 'assignsubmission_file', 'submission_files',
            $submission->id, 'filepath, filename', false);
        foreach ($files as $file) {
            $text = $file->get_filesize() <= self::MAX_FILE_BYTES ? self::file_text($file) : null;
            if ($text === null) {
                $skipped[] = $file->get_filename();
            } else {
                $parts[] = $text;
            }
        }

        return [
            'text' => \core_text::substr(trim(implode("\n\n", $parts)), 0, self::MAX_CHARS),
            'skipped' => $skipped,
        ];
    }

    /**
     * Text of one file, or null if this kind of file is not read.
     *
     * @param \stored_file $file
     * @return string|null
     */
    public static function file_text(\stored_file $file): ?string {
        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        if ($extension === 'ipynb') {
            return self::notebook_text($file->get_content());
        }
        if ($extension === 'docx') {
            return self::zipped_xml_text($file, 'word/document.xml');
        }
        if ($extension === 'odt') {
            return self::zipped_xml_text($file, 'content.xml');
        }
        if (in_array($extension, self::TEXT_EXTENSIONS, true)) {
            $text = $file->get_content();
            if (!mb_check_encoding($text, 'UTF-8')) {
                $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
            }
            return in_array($extension, ['html', 'htm'], true) ? html_to_text($text, 0, false) : $text;
        }
        return null;
    }

    /**
     * Code and markdown cell sources of a notebook (outputs are left out: identical outputs are expected).
     *
     * @param string $json
     * @return string|null
     */
    public static function notebook_text(string $json): ?string {
        $nb = json_decode($json, true);
        if (!is_array($nb) || !isset($nb['cells']) || !is_array($nb['cells'])) {
            return null;
        }
        $parts = [];
        foreach ($nb['cells'] as $cell) {
            $src = $cell['source'] ?? '';
            $parts[] = is_array($src) ? implode('', $src) : (string) $src;
        }
        return implode("\n\n", $parts);
    }

    /**
     * Text of an XML document inside a zip (docx, odt), paragraphs on their own lines.
     *
     * @param \stored_file $file
     * @param string $entry
     * @return string|null
     */
    protected static function zipped_xml_text(\stored_file $file, string $entry): ?string {
        $tmp = $file->copy_content_to_temp();
        $zip = new \ZipArchive();
        $text = null;
        if ($tmp && $zip->open($tmp) === true) {
            $xml = $zip->getFromName($entry);
            $zip->close();
            if ($xml !== false) {
                $xml = preg_replace('#</(w:p|text:p|text:h)>#', "\n", $xml);
                $text = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
            }
        }
        if ($tmp) {
            @unlink($tmp);
        }
        return $text;
    }
}
