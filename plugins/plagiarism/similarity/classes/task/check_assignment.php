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

namespace plagiarism_similarity\task;

/**
 * Compares an assignment's submissions (queued when a student uploads or submits).
 *
 * @package   plagiarism_similarity
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_assignment extends \core\task\adhoc_task {

    public function get_name() {
        return get_string('task_check', 'plagiarism_similarity');
    }

    public function execute() {
        $cmid = (int) $this->get_custom_data()->cmid;
        $n = \plagiarism_similarity\local\checker::run($cmid);
        mtrace("plagiarism_similarity: course module {$cmid}, {$n} submission(s) compared.");
    }
}
