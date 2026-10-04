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

namespace theme_iiitdwd\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/renderer.php');

use core\output\html_writer;

/**
 * Calendar renderer: shows the "Events key" as a panel beside the calendar instead of in the blocks drawer.
 *
 * calendar/view.php adds the key as a pretend block on the right, which Boost puts in the closed blocks
 * drawer. Here the key is kept back and output by complete_layout() next to the calendar.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_calendar_renderer extends \core_calendar_renderer {
    /** @var \block_contents|null The Events key, when kept back from the blocks drawer. */
    protected $eventskey = null;

    /**
     * Adds a pretend calendar block, except the Events key on the calendar page.
     *
     * @param \block_contents $bc
     * @param mixed $pos
     */
    public function add_pretend_calendar_block(\block_contents $bc, $pos = BLOCK_POS_RIGHT) {
        if ($this->page->pagetype === 'calendar-view' && $bc->title === get_string('eventskey', 'calendar')) {
            $this->eventskey = $bc;
            return;
        }
        parent::add_pretend_calendar_block($bc, $pos);
    }

    /**
     * Start of the calendar layout, wrapped in a two-column grid when there is an Events key.
     *
     * @return string
     */
    public function start_layout() {
        $html = parent::start_layout();
        return $this->eventskey ? html_writer::start_div('iiitdwd-cal-layout') . $html : $html;
    }

    /**
     * End of the calendar layout, followed by the Events key panel.
     *
     * @return string
     */
    public function complete_layout() {
        $html = parent::complete_layout();
        if (!$this->eventskey) {
            return $html;
        }
        $key = html_writer::tag('h2', $this->eventskey->title, ['class' => 'iiitdwd-tdash-card-title']) .
            $this->eventskey->content;
        return $html . html_writer::tag('aside', $key, [
            'class' => 'iiitdwd-tdash-card iiitdwd-cal-key',
            'aria-label' => $this->eventskey->title,
        ]) . html_writer::end_div();
    }
}
