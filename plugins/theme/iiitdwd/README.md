# theme_iiitdwd

IIIT Dharwad dark theme for Moodle: a Boost child theme built on the IIIT Dharwad design system
(deep navy surfaces, blue and green accents, 8px controls, 14px cards).

## Requirements

### Runtime (what the Moodle site needs)

| Requirement | Version | Notes |
|---|---|---|
| Moodle | 5.0+ (`2025041400`) | Tested on 5.0.1 (`bitnamilegacy/moodle`) |
| PHP | 8.2+ | Whatever your Moodle 5.0 needs; tested on 8.2.29 |
| `theme_boost` | Bundled with Moodle 5.0 | Parent theme: layouts, templates, Bootstrap 5.3 and Moodle core SCSS come from it |

There are no npm, Composer or pip runtime dependencies. Moodle compiles the SCSS itself (bundled
scssphp) when caches are purged, so nothing has to be built before deploying.

### Development (optional, only for the scripts below)

| Requirement | Version | Used for |
|---|---|---|
| Node.js | 20+ | `npm run lint` |
| npm packages | see `package.json` | `stylelint`, `stylelint-config-standard-scss` |
| Docker + running `moodle_app` container | – | `npm run check`, `npm run deploy` |
| bash, tar | – | the `tools/*.sh` scripts |

## Layout

```
theme/iiitdwd/
├── version.php                  Plugin metadata
├── config.php                   Boost config + this theme's SCSS callbacks, CSS post-processor and layouts
├── lib.php                      SCSS callbacks, pluginfile (institution logo), Day/Night: user preference
│                                definition and the CSS post-processor (compiled palette -> runtime tokens)
├── settings.php                 Theme settings: Institution logo, Login support email
├── lang/en/theme_iiitdwd.php    Strings
├── classes/privacy/provider.php Privacy API (stores no data)
├── classes/local/student_sql.php             "Active students of these courses" subquery (shared)
├── classes/local/teacher_dashboard_data.php  Teacher dashboard queries
├── classes/local/live_session_stats.php      Zoom attendance for the course page
├── classes/local/color_mode.php              Day/Night mode: preference, cookie, default
├── classes/local/user_role.php               Role shown in the role badge (Teacher, else highest active role)
├── classes/output/teacher_dashboard.php      Teacher dashboard template context
├── classes/output/course_nav.php              Course navigation sub-sidebar context (from $PAGE->secondarynav)
├── classes/output/course_hero.php             Course hero + Live Session Stats context
├── classes/output/core_renderer.php          Adds the dashboard / course hero; body class for the sub-sidebar
├── classes/output/core_calendar_renderer.php Moves the calendar's Events key beside the calendar
├── templates/teacher_dashboard.mustache      Teacher dashboard markup (incl. Your courses)
├── templates/course_nav.mustache              Course navigation sub-sidebar (+ course_nav_item, course_nav_chevron)
├── templates/course_hero.mustache            Course hero + Live Session Stats markup
├── templates/core_calendar/month_detailed.mustache  Override of core: time, topic and course per event + Zoom Join link
├── templates/core_calendar/event_summary_modal.mustache  Override of core: event popup footer (Join Zoom meeting button)
├── templates/core_calendar/event_details.mustache  Override of core: course right after the time
├── templates/zoom_icon.mustache              Video icon for Join links
├── templates/theme_boost/navbar.mustache     Override of Boost: no top nav items, Manage menu, sidebar button
├── templates/app_sidebar.mustache            App shell sidebar: institution logo, role badge, Dashboard / My courses / Calendar
├── templates/color_mode_toggle.mustache      Day/Night switch (navbar, beside notifications)
├── templates/role_badge.mustache             Role pill (sidebar and Dashboard heading)
├── templates/core/full_header.mustache       Override of core: role badge beside the Dashboard heading, course sub-sidebar
├── templates/core/loginform.mustache         Override of core: login page (institution logo, pill inputs, support contact)
├── templates/manage_menu.mustache            Manage gear menu for site admins
├── scss/tokens.scss             Design tokens ($iiitdwd-*, Night and Day), the single source of the palette
├── scss/color_modes.scss        Day/Night CSS custom properties (:root = Day, [data-theme="dark"] = Night)
├── scss/preset/default.scss     Tokens as Bootstrap/Moodle variables (incl. dark alert colours), then the Boost import stack
├── scss/editor.scss             TinyMCE editing area (compiled separately via $THEME->editor_scss)
├── scss/styles.scss             --iiitdwd-* CSS custom properties and dark overrides
├── scss/teacher_dashboard.scss  Teacher dashboard and Your courses styles (imported by styles.scss)
├── scss/course.scss             Course sub-sidebar, hero, Live Session Stats, section cards
├── scss/calendar.scss           Calendar filter bar, month grid, event pills, Events key, event popup
├── scss/elements.scss           Alerts and error pages, forms, selects, search, modals, TinyMCE toolbar/menus, tables,
│                                hover/press states
├── scss/pages.scss              Login page, activity pages, grade user report, notifications, course listing
├── scss/messaging.scss          Navbar chat/notification counters, messaging drawer and chat bubbles
├── scss/ipynb.scss              Notebook viewer modal, dark code cells, outputs and tables
├── scss/shell.scss              App shell: sidebar, navbar offset, Manage menu, mobile off-canvas
├── amd/src/theme_toggle.js      Day/Night switch (plain AMD; amd/build/theme_toggle.min.js is a copy)
├── amd/src/ipynb_viewer.js      Jupyter notebook viewer (plain AMD; amd/build/ipynb_viewer.min.js is a copy)
├── classes/external/sanitise_notebook_html.php  Web service: cleans notebook markdown/HTML for the viewer
├── db/services.php              Registers theme_iiitdwd_sanitise_notebook_html
└── tools/                       Dev scripts (not deployed)
```

Layouts: `standard`, `frontpage`, `incourse` and `course` use Boost's `drawers.php`, which replaced
`columns2.php` in Moodle 4.0 (content + `side-pre` blocks, with course index and block drawers).
All other layouts are inherited from Boost.

## App shell

When a user has this theme, every page with the normal Moodle layout (drawers) becomes an app layout:

- **Left sidebar** with the theme's sections only: Dashboard, My courses, Calendar. The current section is
  highlighted (course pages count as My courses). On screens narrower than 992px it is hidden and opens from
  the navbar's menu button (closes on the backdrop or Escape).
- **Top bar** keeps notifications, messages, the user menu and Edit mode. Moodle's top navigation (Home,
  Dashboard, My courses, Site administration) is removed; its pages are reached from the sidebar or Manage.
- **Manage** (gear, top right), for site admins only: Site administration, then shortcuts to Users, Courses
  and categories, Plugins, Themes, Notifications and upgrades, Purge caches. The label is shown from 1200px;
  below that only the gear.
- On course pages the course sub-sidebar is used while the course index is closed (or on screens 1600px and
  wider); with the course index open, Boost's horizontal course tabs are shown so the content keeps its width.

Popups, embedded pages and the login page keep their own layouts. `templates/theme_boost/navbar.mustache` is a
copy of Moodle 5.0.1's Boost navbar: compare it with `theme/boost/templates/navbar.mustache` after upgrading.

## Teacher dashboard

Users who teach at least one course see a teacher panel at the top of their Dashboard (`/my/`), above the
usual blocks (Timeline, Calendar). Nothing has to be added or reset on the dashboard: the theme's
`core_renderer::course_content_header()` renders it, so it appears only for users on this theme.

"Teaching" means actively enrolled with `moodle/grade:viewall` in the course (editing teacher, non-editing
teacher, manager). "Students" are users with a student-archetype role and an active enrolment there, so
unenrolled or suspended users are never counted.

| Section | Shows | Source |
|---|---|---|
| Hero | Date, greeting by time of day, counts of submissions to grade, today's live classes and courses. "Grade now" opens the grading page of the top attention item (disabled when nothing is waiting); "Message students" opens messaging | Same data as below |
| This week | Active learners, submissions received, and a Mon–Sun bar per day (today in green, future days dimmed) | `user_lastaccess` since Monday; latest `submitted` assignment submissions, in the teacher's timezone |
| Courses teaching | Number of teaching courses | Enrolments + capability |
| Students enrolled | Distinct students across those courses | Active enrolments with a student role |
| Awaiting grading | Submissions needing grading | Latest submission is `submitted` with no grade, or changed after grading (the assignment grading table's rule) |
| Avg. completion | Mean student progress across courses with completion tracking, or "–" if none track it | `course_modules_completion` (complete or complete-pass) over visible tracked activities |
| Needs your attention | Top 5 assignments by submissions to grade, with course tag, "N to grade" tag, age of the oldest submission, and a link to grading | As "Awaiting grading" |
| Your courses | Up to 6 teaching course cards (alternating dark blue / dark green header) with the course code pill, title, student count, average completion bar, and Grade (grader report), Content (course page), People (participants) buttons; "View all" when there are more | Same student and completion data as the metrics, per course |
| Today's live classes | Zoom and BigBlueButton sessions today with time, course, and Upcoming / Live now / Ended; empty state links to the calendar | Calendar events of `mod_zoom` (one per recurring occurrence) and `mod_bigbluebuttonbn` |

Wording lives in `lang/en/theme_iiitdwd.php` and can be changed under Site administration → Language →
Language customisation (component `theme_iiitdwd`), e.g. the greeting to "Good morning, Dr. {$a}".

The numbers are computed on each Dashboard load (a handful of aggregate queries over the teacher's courses);
nothing is cached, so grading is reflected immediately.

## Course pages

- **Sub-sidebar** (lg and wider): on course-level pages (course page, Participants, Grades, Settings, …)
  Boost's horizontal course navigation is shown as a vertical list left of the content. The renderer adds
  `iiitdwd-course-subnav` to `<body>`; the rest is CSS. The items and their permissions are Moodle's own
  (Course, Settings, Participants, Grades, Activities, More …); Enrolment methods and Groups are reached
  through Participants, as in standard Moodle 4+. Below lg, Boost's tabs are kept.
- **Course hero** (`/course/view.php` only): course code pill, category, title, start/end dates. It replaces
  Boost's page heading there.
- **Live Session Stats**: Zoom attendance from `zoom_meeting_details` (meetings Zoom reports as held) and
  `zoom_meeting_participants` (one attendance per user per meeting). Teachers see the class: attended /
  missed student-sessions and the attendance rate; students see their own sessions. Shows an empty state
  until Zoom has reported a meeting; hidden if mod_zoom is not installed.
- **Sections and activities**: rounded dark cards.

## Calendar

- Filter bar (view, course, "+ New event") and a dark month grid; each event pill shows its time and is
  tinted by event type (course blue, site green, user cyan, group amber, category purple).
- **Events key** beside the calendar (lg and wider; below it underneath). Moodle adds it to the blocks
  drawer; `output\core_calendar_renderer` keeps it back and renders it next to the calendar. Clicking a type
  still shows/hides those events.
- `templates/core_calendar/month_detailed.mustache` overrides core to add the event time and, as in
  theme_vocab, a "Join" link on Zoom events. It is a copy of Moodle 5.0.1's template: compare it with
  `calendar/templates/month_detailed.mustache` after a Moodle upgrade.

## Scripts

Run from this directory after `npm install`:

| Command | What it does |
|---|---|
| `npm run lint` | Lint `scss/**/*.scss` with stylelint |
| `npm run check` | Compile the SCSS with Moodle's compiler inside the container, without installing anything |
| `npm run deploy` | Copy the theme into the container (without dev files), run the Moodle upgrade and purge caches |

The container name and Moodle path can be overridden with `MOODLE_CONTAINER` (default `moodle_app`)
and `MOODLE_DIR` (default `/bitnami/moodle`).

Moodle CLI commands run as `daemon`, never root: root-owned files in moodledata make the site return 500.

## Selecting the theme

The theme is offered as a per-user choice; the site default theme is left unchanged.

1. Enable user themes once: Site administration → Appearance → Themes → Theme settings → Allow user
   themes, or `docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/cfg.php --name=allowuserthemes --set=1`.
2. Each user: Profile → Edit profile → Preferred theme → IIIT Dharwad → Update profile.

Choosing "Use theme" for IIIT Dharwad on the Themes page would make it the site default for everyone instead.

## Site configuration this theme expects

- **No Moodle Docs links**: `config.php` (container volume `/bitnami/moodle/config.php`, not in git) sets
  `$CFG->docroot = '';` above the `require_once(__DIR__ . '/lib/setup.php');` line. That removes "Documentation for
  this page" in the footer and "More help" in help popups. Error pages use `templates/error_card.mustache`
  (`core_renderer::fatal_error()`), which never links to Moodle Docs.
- **Themed 404 page**: `moodle-tuning/apache-event.conf` has `ErrorDocument 404 /error/index.php`, so unknown URLs get
  Moodle's "page not found" page in the site theme.
