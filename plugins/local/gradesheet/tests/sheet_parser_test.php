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

namespace local_gradesheet;

use local_gradesheet\local\sheet_parser;

/**
 * Tests for the grade sheet file parser.
 *
 * @package   local_gradesheet
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_gradesheet\local\sheet_parser
 */
final class sheet_parser_test extends \advanced_testcase {
    /**
     * Writes content to a temporary file.
     *
     * @param string $content
     * @return string Path.
     */
    protected function file(string $content): string {
        $path = make_request_directory() . '/sheet';
        file_put_contents($path, $content);
        return $path;
    }

    public function test_csv_with_bom_semicolons_and_blank_rows(): void {
        $parsed = sheet_parser::parse($this->file("\xEF\xBB\xBFRoll No;Marks\r\ns1;42\r\n\r\n\"u2\";\"38,5\"\r\n"), 'm.csv');
        $this->assertSame(['Roll No', 'Marks'], $parsed['headers']);
        $this->assertCount(2, $parsed['rows']);
        $this->assertSame(['line' => 4, 'cells' => ['u2', '38,5']], $parsed['rows'][1]);
    }

    public function test_windows1252_csv(): void {
        $parsed = sheet_parser::parse($this->file("Name,Score\n" . mb_convert_encoding('José', 'Windows-1252', 'UTF-8') . ",10\n"), 'm.csv');
        $this->assertSame('José', $parsed['rows'][0]['cells'][0]);
    }

    public function test_xlsx(): void {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([['Email', 'Score'], ['s1@example.com', 42], ['u2@example.com', '=20+18']]);
        $path = make_request_directory() . '/m.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $parsed = sheet_parser::parse($path, 'm.xlsx');
        $this->assertSame(['Email', 'Score'], $parsed['headers']);
        $this->assertEquals(42, $parsed['rows'][0]['cells'][1]);
        $this->assertEquals(38, $parsed['rows'][1]['cells'][1]);
    }

    public function test_rejects_other_types_and_empty_files(): void {
        $this->expectException(\moodle_exception::class);
        sheet_parser::parse($this->file('x'), 'm.pdf');
    }

    public function test_rejects_header_only(): void {
        $this->expectException(\moodle_exception::class);
        sheet_parser::parse($this->file("Roll,Marks\n"), 'm.csv');
    }
}
