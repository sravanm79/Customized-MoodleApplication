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

use core\navigation\views\secondary;
use core\output\pix_icon;
use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use navigation_node;

/**
 * The course's secondary navigation (Course, Settings, Participants, Grades, ...) as a vertical sub-sidebar
 * (templates/course_nav.mustache), shown left of the content on course-level pages.
 *
 * Built from $PAGE->secondarynav, the same tree as Boost's horizontal tabs: Moodle's main tabs first, then the
 * items it puts under "More" as a collapsible group. Items with children (Participants, Reports, Course reuse, ...)
 * are expandable groups; the group holding the current page starts open, and the current page gets the pill.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_nav implements renderable, templatable {
    /** @var string[] Icons for nodes whose own icon is the generic dot (i/navigationitem), by node key. */
    const ICONS = [
        'coursehome' => 'i/course',
        'editsettings' => 'i/settings',
        'participants' => 'i/users',
        'grades' => 'i/grades',
        'courseoverview' => 'i/info',
        'coursereports' => 'i/stats',
        'questionbank' => 'i/questions',
        'coursereuse' => 'i/backup',
        'override' => 'i/permissions',
        'manageinstances' => 'i/enrolusers',
        'courseblogs' => 'i/news',
        'currentcoursenotes' => 'i/edit',
        'renameroles' => 'i/assignroles',
    ];

    /** @var string[] Items whose sub-pages are not listed. Question banks: since Moodle 5.0 banks are activities, and
     * the course-level Import/Export links need a bank (cmid) and fail; each bank's own page has those tabs. */
    const NO_CHILDREN = ['questionbank'];

    /** @var secondary */
    protected $nav;

    /** @var string Unique per page, for the collapse ids. */
    protected $idprefix;

    /**
     * @param secondary $nav The page's secondary navigation.
     */
    public function __construct(secondary $nav) {
        $this->nav = $nav;
        $this->idprefix = 'iiitdwd-coursenav-' . uniqid();
    }

    /**
     * Template context.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $this->nav->initialise();
        $main = [];
        $more = [];
        foreach ($this->nav->children as $node) {
            if (!$node->display || !$node->action instanceof \moodle_url) {
                continue;
            }
            $item = $this->export_node($node, $output, true);
            if ($node->forceintomoremenu) {
                $more[] = $item;
            } else {
                $main[] = $item;
            }
        }
        $moreopen = (bool) array_filter($more, fn($item) => $item['isactive'] || $item['isopen']);
        return [
            'arialabel' => get_string('coursenavigation', 'theme_iiitdwd'),
            'main' => $main,
            'hasmore' => !empty($more),
            'more' => $more,
            'moreid' => $this->idprefix . '-more',
            'moreopen' => $moreopen,
        ];
    }

    /**
     * One item (its action is a moodle_url), with its children (one level) when $withchildren is set.
     *
     * @param navigation_node $node
     * @param renderer_base $output
     * @param bool $withchildren
     * @return array
     */
    protected function export_node(navigation_node $node, renderer_base $output, bool $withchildren): array {
        $children = [];
        if ($withchildren && !in_array((string) $node->key, self::NO_CHILDREN, true)) {
            $seen = [$node->action->out(false) => true];
            foreach ($node->children as $child) {
                // Individual users (e.g. the current user under Participants) are not course pages.
                if (!$child->display || !$child->action instanceof \moodle_url || $child->type == navigation_node::TYPE_USER) {
                    continue;
                }
                // Moodle lists some pages twice (Check permissions under Participants and under Permissions).
                $url = $child->action->out(false);
                if (isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;
                $children[] = $this->export_node($child, $output, false);
            }
        }
        $activechild = (bool) array_filter($children, fn($child) => $child['isactive']);
        return [
            'text' => (string) $node->get_content(),
            'url' => $node->action->out(false),
            'icon' => $withchildren ? $output->render($this->icon($node)) : '',
            // The pill marks the current page: the child when one is current, else the item itself.
            'isactive' => $node->isactive && !$activechild,
            'hasactivechild' => $activechild,
            'haschildren' => !empty($children),
            'children' => $children,
            'isopen' => $activechild || ($node->isactive && !empty($children)) || ($children && $node->contains_active_node()),
            'collapseid' => $this->idprefix . '-' . clean_param((string) $node->key, PARAM_ALPHANUMEXT) . '-' . $node->type,
        ];
    }

    /**
     * Icon for a top-level item: Moodle's own unless it is the generic dot, else one by key.
     *
     * @param navigation_node $node
     * @return pix_icon
     */
    protected function icon(navigation_node $node): pix_icon {
        if ($node->icon instanceof pix_icon && $node->icon->pix !== 'i/navigationitem' && !isset(self::ICONS[$node->key])) {
            return new pix_icon($node->icon->pix, '', $node->icon->component);
        }
        return new pix_icon(self::ICONS[$node->key] ?? 'i/navigationitem', '');
    }
}
