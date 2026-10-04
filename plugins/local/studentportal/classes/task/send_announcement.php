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
namespace local_studentportal\task;

/**
 * Sends one announcement to its students (queued by local_studentportal\local\announcements::create()).
 *
 * @package    local_studentportal
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_announcement extends \core\task\adhoc_task {

    /**
     * Run.
     */
    public function execute() {
        $data = $this->get_custom_data();
        $sent = \local_studentportal\local\announcements::send((int) $data->id);
        mtrace("Announcement {$data->id}: notified {$sent} student(s).");
    }
}
