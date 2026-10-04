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

use theme_iiitdwd\local\color_mode;
use theme_iiitdwd\local\teacher_dashboard_data;
use theme_iiitdwd\local\user_role;

/**
 * Core renderer for theme_iiitdwd.
 *
 * @package    theme_iiitdwd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {
    /** @var string Default of the Login support email setting (settings.php). */
    const LOGIN_SUPPORT_EMAIL = 'support.dsai@iiitdwd.ac.in';

    /** @var bool Whether the teacher dashboard or course hero has been output on this page. */
    protected $panelrendered = false;

    /**
     * Content header, plus this theme's panel for the page: the teacher dashboard on the Dashboard (/my/)
     * or the course hero on the course page (/course/view.php).
     *
     * Boost renders this directly above the main content.
     *
     * @param bool $onlyifnotcalledbefore
     * @return string
     */
    public function course_content_header($onlyifnotcalledbefore = false) {
        $output = parent::course_content_header($onlyifnotcalledbefore);
        if ($this->panelrendered) {
            return $output;
        }
        if ($this->page->pagetype === 'my-index' && isloggedin() && !isguestuser()) {
            $this->panelrendered = true;
            $output .= $this->teacher_dashboard();
        } else if ($this->is_course_view_page()) {
            $this->panelrendered = true;
            $output .= $this->course_hero();
        }
        return $output;
    }

    /**
     * <html> attributes, adding data-theme (dark or light) from the user's Day/Night preference.
     *
     * @return string
     */
    public function htmlattributes() {
        return parent::htmlattributes() . ' data-theme="' . color_mode::current() . '"';
    }

    /**
     * Head HTML, plus the Jupyter notebook viewer (amd/src/ipynb_viewer.js) for logged-in users: clicking a .ipynb
     * file opens it in a modal instead of downloading it.
     *
     * @return string
     */
    public function standard_head_html() {
        if (isloggedin() && $this->page->pagelayout !== 'embedded') {
            $strings = [];
            foreach (['loading', 'error', 'toolarge', 'download', 'in'] as $key) {
                $strings[$key] = get_string('ipynb' . $key, 'theme_iiitdwd');
            }
            $this->page->requires->js_call_amd('theme_iiitdwd/ipynb_viewer', 'init', [[
                'resourcecmids' => $this->notebook_resource_cmids(),
                'strings' => $strings,
            ]]);
        }
        return parent::standard_head_html();
    }

    /**
     * File resources in this page's course whose main file is a notebook, so their links (which go to
     * mod/resource/view.php, not the file) also open in the viewer. Uses the file details mod_resource caches in
     * the course module info.
     *
     * @return int[] Course module ids.
     */
    protected function notebook_resource_cmids(): array {
        if (empty($this->page->course->id) || $this->page->course->id == SITEID) {
            return [];
        }
        $cmids = [];
        foreach (get_fast_modinfo($this->page->course)->get_instances_of('resource') as $cm) {
            $options = $cm->customdata['displayoptions'] ?? null;
            $options = is_string($options) ? (unserialize_array($options) ?: []) : [];
            if ($cm->uservisible && strtolower($options['filedetails']['extension'] ?? '') === 'ipynb') {
                $cmids[] = (int) $cm->id;
            }
        }
        return $cmids;
    }

    /**
     * Day/Night toggle for the navbar (templates/color_mode_toggle.mustache).
     *
     * @return string
     */
    public function color_mode_toggle(): string {
        $this->page->requires->js_call_amd('theme_iiitdwd/theme_toggle', 'init', [[
            // Guests and logged-out users only have localStorage.
            'persist' => isloggedin() && !isguestuser(),
        ]]);
        return $this->render_from_template('theme_iiitdwd/color_mode_toggle', [
            'isdark' => color_mode::current() === color_mode::DARK,
        ]);
    }

    /** @var string[] Error codes meaning "the link is missing an id or points at something that no longer exists". */
    const ERRORS_BADLINK = ['missingparam', 'unspecifycourseid', 'invalidcourseid', 'invalidrecord', 'invalidrecordunknown',
        'unknowncategory', 'invalidcoursemodule', 'invalidcoursemoduleid', 'invalidid', 'invaliduser', 'invaliduserid',
        'coursehidden', 'cannotfindcategory'];

    /** @var string[] Error codes meaning "you may not see this". */
    const ERRORS_ACCESS = ['nopermissions', 'accessdenied', 'requireloginerror', 'notenrolled', 'noguest',
        'activityiscurrentlyhidden', 'nopermissiontoviewpage'];

    /**
     * Error page: the theme's error card instead of core's box with a "More information" link to Moodle Docs and a
     * bare "Continue" button.
     *
     * Core still produces the page (HTTP status, header, output buffering, developer debug output); its box is
     * swapped for templates/error_card.mustache.
     *
     * @param string $message
     * @param string $moreinfourl Ignored: a Moodle Docs page.
     * @param string $link Where "Go back" goes.
     * @param array $backtrace
     * @param string|null $debuginfo
     * @param string $errorcode
     * @return string
     */
    public function fatal_error($message, $moreinfourl, $link, $backtrace, $debuginfo = null, $errorcode = '') {
        global $CFG;
        // No link and no continue button from core; the card has its own actions.
        $output = parent::fatal_error($message, '', '', $backtrace, $debuginfo, $errorcode);

        $kind = in_array($errorcode, self::ERRORS_BADLINK, true) ? 'badlink'
            : (in_array($errorcode, self::ERRORS_ACCESS, true) ? 'access' : 'generic');
        // Only send people back within the site.
        $back = is_string($link) && strpos($link, $CFG->wwwroot . '/') === 0 ? $link : $CFG->wwwroot . '/';
        $card = $this->render_from_template('theme_iiitdwd/error_card', [
            'title' => get_string('error' . $kind . 'title', 'theme_iiitdwd'),
            'hint' => get_string('error' . $kind . 'hint', 'theme_iiitdwd'),
            'message' => $message,
            'errorcode' => $errorcode,
            'backurl' => $back,
            'dashboardurl' => (new \moodle_url('/my/'))->out(false),
            'searchurl' => (new \moodle_url('/course/search.php'))->out(false),
            'loginurl' => isloggedin() && !isguestuser() ? null : get_login_url(),
        ]);
        $replaced = preg_replace('~<div[^>]*data-rel="fatalerror"[^>]*>.*?</div>~s', $card, $output, 1, $count);
        return $count ? $replaced : $output;
    }

    /**
     * Body attributes, adding iiitdwd-course-subnav on course-level pages that have a secondary navigation.
     * The SCSS turns that navigation into a left sub-sidebar on those pages.
     *
     * @param string|array $additionalclasses
     * @return string
     */
    public function body_attributes($additionalclasses = []) {
        if (!is_array($additionalclasses)) {
            $additionalclasses = explode(' ', $additionalclasses);
        }
        if ($this->is_course_subnav_page()) {
            $additionalclasses[] = 'iiitdwd-course-subnav';
        }
        if ($this->is_course_view_page()) {
            $additionalclasses[] = 'iiitdwd-has-course-hero';
        }
        // The app sidebar is output by the navbar, which the drawers layout renders (not popups, login, ...).
        $layouts = $this->page->theme->layouts;
        if (($layouts[$this->page->pagelayout]['file'] ?? '') === 'drawers.php') {
            $additionalclasses[] = 'iiitdwd-app-shell';
        }
        return parent::body_attributes($additionalclasses);
    }

    /**
     * App shell sidebar (the theme's sections), rendered from the navbar template.
     *
     * @return string
     */
    public function app_sidebar(): string {
        $items = [
            ['dashboard', get_string('myhome'), new \moodle_url('/my/'), 'i/dashboard'],
            ['mycourses', get_string('mycourses'), new \moodle_url('/my/courses.php'), 'i/course'],
            ['calendar', get_string('calendar', 'calendar'), new \moodle_url('/calendar/view.php', ['view' => 'month']),
                'i/calendar'],
        ];
        $active = $this->active_app_section();
        $context = [
            'homeurl' => (new \moodle_url('/my/'))->out(false),
            'institutionname' => get_string('institutionname', 'theme_iiitdwd'),
            'institutionlogourl' => $this->page->theme->setting_file_url('institutionlogo', 'institutionlogo') ?? '',
            'rolebadge' => $this->role_badge_context('sidebar'),
            'items' => [],
        ];
        foreach ($items as [$key, $text, $url, $icon]) {
            $context['items'][] = [
                'text' => $text,
                'url' => $url->out(false),
                'icon' => $this->pix_icon($icon, ''),
                'isactive' => $key === $active,
            ];
        }
        return $this->render_from_template('theme_iiitdwd/app_sidebar', $context);
    }

    /**
     * Context for the role badge (templates/role_badge.mustache) of the logged-in user.
     *
     * @param string $variant sidebar or heading
     * @return array|null key, label, arialabel, variant; null for guests and users without a role.
     */
    protected function role_badge_context(string $variant): ?array {
        global $USER;
        if (!isloggedin() || isguestuser()) {
            return null;
        }
        $badge = user_role::badge((int) $USER->id);
        if ($badge === null) {
            return null;
        }
        $badge['arialabel'] = get_string('yourrole', 'theme_iiitdwd', $badge['label']);
        $badge['variant'] = $variant;
        return $badge;
    }

    /**
     * Adds this theme's data to core templates it overrides:
     * - core/full_header (templates/core/full_header.mustache): the role badge beside the Dashboard heading, and the
     *   course navigation sub-sidebar on course-level pages;
     * - core/loginform (templates/core/loginform.mustache): the institution logo and the support contact.
     *
     * @param string $templatename
     * @param array|\stdClass $context
     * @return string
     */
    public function render_from_template($templatename, $context) {
        if ($templatename === 'core/full_header' && is_object($context)) {
            if ($this->page->pagetype === 'my-index') {
                $context->iiitdwdrolebadge = $this->role_badge_context('heading');
            }
            if ($this->is_course_subnav_page()) {
                $context->iiitdwdcoursenav = $this->render_from_template('theme_iiitdwd/course_nav',
                    (new course_nav($this->page->secondarynav))->export_for_template($this));
            }
        }
        if ($templatename === 'core/loginform' && is_object($context)) {
            $context->institutionname = get_string('institutionname', 'theme_iiitdwd');
            $context->institutionlogourl = $this->page->theme->setting_file_url('institutionlogo', 'institutionlogo') ?? '';
            // Until the setting is saved, get_config() returns false: use its default.
            $email = get_config('theme_iiitdwd', 'loginsupportemail');
            $context->supportemail = clean_param($email === false ? self::LOGIN_SUPPORT_EMAIL : $email, PARAM_EMAIL);
        }
        return parent::render_from_template($templatename, $context);
    }

    /**
     * Which sidebar section the current page belongs to.
     *
     * @return string|null 'dashboard', 'mycourses', 'calendar' or null.
     */
    protected function active_app_section(): ?string {
        if (!$this->page->has_set_url()) {
            return null;
        }
        $url = $this->page->url;
        if ($url->compare(new \moodle_url('/my/courses.php'), URL_MATCH_BASE)) {
            return 'mycourses';
        }
        if ($url->compare(new \moodle_url('/my/'), URL_MATCH_BASE)
                || $url->compare(new \moodle_url('/my/index.php'), URL_MATCH_BASE)) {
            return 'dashboard';
        }
        if ($this->page->pagetype !== null && strpos($this->page->pagetype, 'calendar-') === 0) {
            return 'calendar';
        }
        // Course pages and everything inside a course belong to My courses.
        if ($this->page->course->id != SITEID) {
            return 'mycourses';
        }
        return null;
    }

    /**
     * "Manage" gear menu for site admins: Site administration and admin shortcuts. Empty for everyone else.
     *
     * @return string
     */
    public function manage_menu(): string {
        if (!isloggedin() || isguestuser() || !is_siteadmin()) {
            return '';
        }
        $shortcuts = [
            ['manageusers', new \moodle_url('/admin/user.php'), 'i/users'],
            ['managecourses', new \moodle_url('/course/management.php'), 'i/course'],
            ['manageplugins', new \moodle_url('/admin/plugins.php'), 'i/customfield'],
            ['managethemes', new \moodle_url('/admin/themeselector.php'), 'i/preview'],
            ['managenotifications', new \moodle_url('/admin/index.php'), 'i/notifications'],
            ['managepurgecaches', new \moodle_url('/admin/purgecaches.php'), 'i/reload'],
        ];
        $context = [
            'icon' => $this->pix_icon('i/settings', ''),
            'siteadmin' => [
                'text' => get_string('administrationsite'),
                'url' => (new \moodle_url('/admin/search.php'))->out(false),
                'icon' => $this->pix_icon('i/settings', ''),
                'isactive' => $this->page->pagelayout === 'admin',
            ],
            'shortcuts' => [],
        ];
        foreach ($shortcuts as [$key, $url, $icon]) {
            $context['shortcuts'][] = [
                'text' => get_string($key, 'theme_iiitdwd'),
                'url' => $url->out(false),
                'icon' => $this->pix_icon($icon, ''),
            ];
        }
        return $this->render_from_template('theme_iiitdwd/manage_menu', $context);
    }

    /**
     * The course hero with Live Session Stats.
     *
     * @return string
     */
    public function course_hero(): string {
        global $USER;
        $hero = new course_hero($this->page->course, $USER);
        return $this->render_from_template('theme_iiitdwd/course_hero', $hero->export_for_template($this));
    }

    /**
     * Whether this page shows the course navigation as a left sub-sidebar: course-level pages (course home,
     * participants, grades, settings, reports, ...) that have a secondary navigation.
     *
     * @return bool
     */
    protected function is_course_subnav_page(): bool {
        return $this->page->course->id != SITEID && $this->page->context->contextlevel == CONTEXT_COURSE
            && $this->page->has_secondary_navigation();
    }

    /**
     * Whether this is a course's main page, /course/view.php (any course format).
     *
     * The page type alone is not enough: other course pages such as Participants also use "course-view-*".
     *
     * @return bool
     */
    protected function is_course_view_page(): bool {
        return $this->page->course->id != SITEID && strpos($this->page->pagetype, 'course-view-') === 0
            && $this->page->has_set_url() && $this->page->url->compare(new \moodle_url('/course/view.php'), URL_MATCH_BASE);
    }

    /**
     * The teacher dashboard for the current user, or '' if they teach no courses.
     *
     * @return string
     */
    public function teacher_dashboard(): string {
        global $USER;
        $dashboard = new teacher_dashboard(new teacher_dashboard_data($USER));
        if (!$dashboard->should_display()) {
            return '';
        }
        return $this->render_from_template('theme_iiitdwd/teacher_dashboard', $dashboard->export_for_template($this));
    }
}
