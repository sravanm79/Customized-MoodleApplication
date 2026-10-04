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

namespace local_gradesheet\local;

use moodle_exception;

/**
 * Reads an uploaded grade sheet (CSV, Excel .xlsx/.xls, OpenDocument .ods) into a header row and data rows.
 *
 * CSV: UTF-8 (with or without the BOM Excel adds) or Windows-1252, separated by commas, semicolons or tabs
 * (detected from the header line), with quoted fields. Spreadsheets: the first worksheet, cell values (formulas
 * calculated). Blank rows are skipped; the first non-blank row is the header.
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sheet_parser {
    /** @var int Maximum data rows read. */
    const MAX_ROWS = 5000;

    /** @var int Maximum columns read. */
    const MAX_COLUMNS = 50;

    /** @var string[] Accepted file extensions. */
    const EXTENSIONS = ['csv', 'txt', 'xlsx', 'xls', 'ods'];

    /**
     * Parses a file.
     *
     * @param string $path File on disk.
     * @param string $filename Original name (its extension picks the reader).
     * @return array ['headers' => string[], 'rows' => array of ['line' => int, 'cells' => string[]]]
     * @throws moodle_exception For unsupported, unreadable or empty files.
     */
    public static function parse(string $path, string $filename): array {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw new moodle_exception('errorfiletype', 'local_gradesheet');
        }
        $lines = in_array($extension, ['csv', 'txt'], true) ? self::read_csv($path) : self::read_spreadsheet($path);

        // Number the lines as the teacher sees them (1-based), then drop blank ones.
        $rows = [];
        foreach ($lines as $index => $cells) {
            $cells = array_map(fn($cell) => trim((string) $cell), array_slice($cells, 0, self::MAX_COLUMNS));
            if (implode('', $cells) !== '') {
                $rows[] = ['line' => $index + 1, 'cells' => $cells];
            }
        }
        if (count($rows) < 2) {
            throw new moodle_exception('errorempty', 'local_gradesheet');
        }
        $header = array_shift($rows);
        if (count($rows) > self::MAX_ROWS) {
            throw new moodle_exception('errortoomanyrows', 'local_gradesheet', '', self::MAX_ROWS);
        }
        return ['headers' => $header['cells'], 'rows' => $rows];
    }

    /**
     * CSV lines.
     *
     * @param string $path
     * @return array[]
     */
    protected static function read_csv(string $path): array {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new moodle_exception('errorread', 'local_gradesheet');
        }
        // Excel's UTF-8 byte order mark; files saved as "CSV" by older Excel are Windows-1252.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $delimiter = self::detect_delimiter(strtok($content, "\n") ?: '');

        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $lines = [];
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $lines[] = $cells;
            if (count($lines) > self::MAX_ROWS + 50) {
                break;
            }
        }
        fclose($handle);
        return $lines;
    }

    /**
     * The separator used in a header line: the most frequent of comma, semicolon and tab.
     *
     * @param string $line
     * @return string
     */
    protected static function detect_delimiter(string $line): string {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);
        return reset($counts) > 0 ? array_key_first($counts) : ',';
    }

    /**
     * First worksheet of an Excel or OpenDocument file.
     *
     * @param string $path
     * @return array[]
     */
    protected static function read_spreadsheet(string $path): array {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (\Throwable $e) {
            throw new moodle_exception('errorread', 'local_gradesheet', '', null, $e->getMessage());
        }
        $lastrow = min($sheet->getHighestDataRow(), self::MAX_ROWS + 50);
        $lastcolumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(
            min(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()),
                self::MAX_COLUMNS));
        // Calculated values; numbers stay numbers (no locale formatting), empty cells become ''.
        return $sheet->rangeToArray('A1:' . $lastcolumn . $lastrow, '', true, false, false);
    }
}
