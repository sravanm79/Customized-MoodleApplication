# Customized-MoodleApplication

Moodle 5.0.1 (Bitnami, Docker) for IIIT Dharwad with a custom theme, proctoring, LLM grading, grade sheets,
Jupyter notebooks, CodeRunner and Zoom.

## Services and addresses

| What | Address | Notes |
|---|---|---|
| **LMS** | **https://192.168.30.239** | The only address (`$CFG->wwwroot`). Old `http://localhost:9999` links redirect here. Each device installs the LMS certificate once: [docs/https-and-student-devices.md](docs/https-and-student-devices.md). |
| LMS certificate (CA) | http://192.168.30.239:9999/lms-ca.crt | Plain http on purpose, so it can be fetched before https is trusted. |
| Jupyter notebooks | https://192.168.30.239/jupyter/ | One isolated container per student: [docs/jupyter.md](docs/jupyter.md). |
| Mail inbox (Mailpit) | https://192.168.30.239/mail/ | User `admin`, password `MAILPIT_UI_PASSWORD` in `.env`: [docs/email.md](docs/email.md). |
| CodeRunner sandbox | `jobe` (internal only) | |
| LLM for auto-grading | `http://45.194.46.66:9010/v1` (model `surya2`) | [plugins/local/llmgrader/README.md](plugins/local/llmgrader/README.md) |

Containers: `moodle_app`, `moodle_db`, `moodle_jobe`, `moodle_jupyterhub` (+ `jupyter-moodle<id>` per student),
`moodle_mailpit`. Secrets live only in `.env` (git-ignored; keys listed in `.env.example`), `tls/ca/`, `tls/server/`
and `mailpit/secrets/` (all git-ignored).

Student onboarding (register students, share logins) and the student dashboard / My performance:
[plugins/local/studentportal/README.md](plugins/local/studentportal/README.md).

Other guides: [Safe Exam Browser + proctoring](docs/seb-proctoring.md) ·
[**Manual testing guide**](docs/MANUAL-TESTING.md) · load tests in `locust/` and `docs/`.

## Plugins

| Plugin (in `plugins/`) | Version | What it does |
|---|---|---|
| `theme/iiitdwd` | 0.8.2 | IIIT Dharwad theme, teacher and student dashboards, course hero, calendar ([README](plugins/theme/iiitdwd/README.md)) |
| `local/studentportal` | 0.2.0 | Register students and share logins, announcements, My performance, class overview ([README](plugins/local/studentportal/README.md)) |
| `local/llmgrader` | 0.4.0 | LLM grading of assignments with teacher review; letters from the gradebook ([README](plugins/local/llmgrader/README.md)) |
| `local/gradesheet` | 0.1.1 | Upload CSV/Excel grade sheets; class statistics ([README](plugins/local/gradesheet/README.md)) |
| `local/notifier` | 0.2.0 | Reminders before deadlines and live classes (Zoom, Google Meet), per-course lead times, Google Calendar sync |
| `local/helpdesk` | 0.2.0 | "?" button on every page to raise a ticket; My Tickets; admin dashboard |
| `plagiarism/similarity` | 0.1.0 | Compares assignment submissions with classmates' submissions (new) |
| `quiz/accessrule/examproctor` | 0.3.0 | Proctored exam mode, webcam video recording, live exam monitor |
| `quiz/accessrule/proctoring` | upstream | Webcam photos every 30 s, face matching (needs an API key) |
| `mod/jupyter` | 0.1.0 | Jupyter notebook activity ([docs/jupyter.md](docs/jupyter.md)) |
| `mod/zoom` | 5.5.2 | Zoom meetings; auto-import of cloud recordings |
| `mod/googlemeet` | 2.1.1+iiitdwd.2 | Google Meet, one-click join, scheduled Drive recording sync |
| `question/type/coderunner` + behaviour | upstream | Programming questions run in the `jobe` sandbox |

## New in November 2026 (test matrix P-01 to P-15)

| Task | What was built | Where to find it |
|---|---|---|
| P-04 Support tickets | **Fixed:** submitting a ticket always failed (namespace bug in `ticket_manager`). **New:** *My Tickets* page, link to the new ticket after submitting, students reply on their own tickets (reopens a resolved ticket, notifies staff) | User menu › **My Tickets** (`/local/helpdesk/my.php`); "?" button › *My Tickets* |
| P-07 Reminder timers | Teachers set their own lead times per course (site defaults stay); reminders now also cover **Google Meet** and **every occurrence of recurring Zoom** classes; one reminder per lead time even after downtime | Course › More › **Reminder settings**; site defaults: Site administration › Plugins › Local plugins › Deadline Notifier |
| P-12 Google Calendar | "Google Calendar Sync" link for every user once the OAuth client is set; events use the student's timezone | User menu › Preferences › **Google Calendar Sync** |
| P-05 Meet recordings | Hourly task imports Drive recordings of meetings held in the last 3 days, as a teacher of the course who connected Google once | Scheduled task *Import Google Drive recordings of recent meetings* |
| P-13 Letter grades | LLM letters now come from the **gradebook letters** of the course, so feedback and gradebook always agree. The upgrade sets the site default letters to the institute scale (A 90, A- 85, B 80, B- 75, C 65, D 50, F) unless they were customised | Site administration › Grades › Letters |
| P-08 Plagiarism | New `plagiarism_similarity`: each submission (online text, code/text files, notebooks, Word, OpenDocument; not PDF) is compared with the classmates' submissions; % badge beside every submission, report, side-by-side comparison with shared passages highlighted. Starter code, the assignment description and passages most of the class shares are not counted. Nothing leaves the server | Assignment settings › **Similarity check**; badge in *View all submissions* |
| P-09 Video proctoring | Quiz setting **Record webcam video**: 320×240 video, no sound, uploaded in 10-second pieces while the student writes (a page change loses at most ~10 s), replayed per attempt as one-minute segments. ~70 MB per student-hour at 150 kbps; deleted after 30 days (both configurable) | Quiz settings › Proctored exam; Site administration › Plugins › Activity modules › Quiz › Proctored exam |
| P-10 Live exam monitor | One page with every student writing the exam: latest webcam photo, active / idle / offline, time left, page, violations, video status; counts (writing, with violations, offline, submitted, not started) and the latest violations; refreshes every 15 s | Quiz page › **Live exam monitor** (teachers), or the proctoring report |
| P-02 Attendance, P-15 Certificates | Not vendored yet: add `mod_attendance` and `mod_customcert` (steps below) | |

Still needs accounts or decisions outside the code: Zoom cloud recording plan (P-01, P-06), Google OAuth credentials,
which need a domain name (P-05, P-12, Meet auto-record), the reference design (P-03), the Moodle app's Airnotifier key and
a publicly reachable address (P-11), and a face-matching API key (P-14).

## Deploy plugin changes

`tools/deploy-plugins.sh` copies plugins into `moodle_app`, fixes ownership and permissions, then runs the Moodle
upgrade and purges caches once (as `daemon`). With no arguments it deploys every plugin under `plugins/`.

```bash
# 1. Back up the database first (the upgrade adds tables and fields).
docker exec moodle_db sh -c 'mariadb-dump -ubn_moodle -p"$MARIADB_PASSWORD" bitnami_moodle' | gzip > backup-$(date +%F).sql.gz

# 2. Deploy what changed in November 2026.
tools/deploy-plugins.sh local/helpdesk local/notifier local/llmgrader mod/googlemeet \
    quiz/accessrule/examproctor plagiarism/similarity

# 3. Settings for the new features.
CFG="docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/cfg.php"
$CFG --name=enableplagiarism --set=1                                  # plagiarism plugins on
$CFG --component=plagiarism_similarity --name=enabled --set=1         # similarity check offered in assignments
$CFG --component=zoom --name=viewrecordings --set=1                   # P-01: Zoom recordings shown and auto-imported
$CFG --component=zoom --name=recordingoption --set=cloud              # P-01: new Zoom meetings record to the cloud
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/purge_caches.php
```

The upgrade prints each plugin it upgrades (`local_helpdesk` 0.2.0, `local_notifier` 0.2.0, `local_llmgrader` 0.4.0,
`mod_googlemeet`, `quizaccess_examproctor` 0.3.0, and `plagiarism_similarity` as a new install). Check them under
Site administration › Plugins › Plugins overview. Tasks that run later (reminders, similarity checks, video clean-up)
need Moodle cron, which the Bitnami container runs every minute.

### Add attendance (P-02) and certificates (P-15)

Both are third-party plugins with Moodle 5.0 branches. Vendor them into `plugins/mod/` (their git metadata is not kept),
then deploy:

```bash
git clone --depth 1 -b MOODLE_500_STABLE https://github.com/danmarsden/moodle-mod_attendance.git plugins/mod/attendance
git clone --depth 1 -b MOODLE_500_STABLE https://github.com/mdjnelson/moodle-mod_customcert.git plugins/mod/customcert
rm -rf plugins/mod/attendance/.git plugins/mod/customcert/.git
tools/deploy-plugins.sh mod/attendance mod/customcert
```

Then: **Attendance**: in a course, *Add an activity › Attendance*, add sessions, take attendance (Present / Late /
Excused / Absent) and use *Report* / *Export*. **Certificates**: Site administration › Plugins › Activity modules ›
Custom certificate › *Manage templates* › add the IIIT Dharwad template (border, logo image, student name, course
name, date, code); in a course, add *Custom certificate*, load the template and restrict access to course completion.

### Test the new features

| Feature | Test |
|---|---|
| Tickets | As a student: "?" › Bug report › Submit: the success message links to the ticket. User menu › My Tickets lists it. As admin: reply on the ticket; the student gets a notification, replies, and the admin is notified |
| Reminders | As a teacher: course › More › Reminder settings › Assignments `30`. Create an assignment due in 25 min: within 5 min each student gets a "due in 30 minutes" notification. A Google Meet class starting within the live-class lead time is reminded too |
| Letters | `/admin/` › Grades › Letters shows A 90 … F 0. Approve an LLM grade of 85 %: the LLM feedback and the gradebook both show **A-** |
| Similarity | Assignment settings › Similarity check › Compare submissions = Yes. Two students submit the same text with small changes, a third different text. After a minute (or *Check now* on the report) *View all submissions* shows a red badge for the first two; *Compare* highlights the shared passages |
| Live monitor | Quiz with Proctored exam mode and Record webcam video = Yes. Start an attempt as a student (allow the camera; the bar shows **● REC**). As the teacher, open *Live exam monitor*: the student is *Active* with *Recording*; switch tabs as the student and the violation appears within 15 s. After a minute, *Video* plays the recording |
| Meet sync | Needs Google OAuth (see above): after a Meet class with a recording, the recording appears on the activity within the hour without pressing *Sync with Google Drive* |

### First start on a new server

```bash
cp .env.example .env                       # fill in the passwords and tokens
tls/make-certs.sh 192.168.30.239           # LMS CA + server certificate (use the server's fixed IP or DNS name)
mailpit/configure.sh                       # Mailpit inbox password (+ optional SMTP relay)
docker compose --profile build-only build jupyter-singleuser   # image of each student's Jupyter server
docker compose up -d --build
```

On first install also set in `/bitnami/moodle/config.php`: `$CFG->wwwroot = 'https://192.168.30.239';`, and in
Moodle: *Outgoing mail* SMTP host `mailpit:1025`, `mod_jupyter | hubinternalurl` = `http://jupyterhub:8000/jupyter`,
`hubpublicurl` = `https://192.168.30.239/jupyter`, `hubtoken` = `MOODLE_SERVICE_TOKEN`.

## IIIT Dharwad theme (`theme_iiitdwd`)

A dark Boost child theme in [plugins/theme/iiitdwd/](plugins/theme/iiitdwd/). Requirements, file layout
and the `npm` scripts are in [its README](plugins/theme/iiitdwd/README.md). The steps below restart the
application, deploy and verify the theme, and test it from a fresh checkout.

> Run every Moodle CLI command with `docker exec -u daemon`. Running it as root leaves root-owned
> files in moodledata, and the site then returns HTTP 500.

### 1. Start (or restart) the stack

```bash
cp .env.example .env          # first time only; fill in the passwords
docker compose up -d          # start everything
docker compose restart moodle # or: restart only Moodle
docker compose ps             # moodle_app should be "Up"
```

Wait until `curl -s --cacert tls/public/iiitdwd-lms-ca.crt -o /dev/null -w "%{http_code}\n" https://192.168.30.239/login/index.php` prints `200`
(the first start can take a few minutes while Bitnami installs Moodle).

The theme is stored in the `moodle_data` volume, so it survives `restart`, `stop`/`start` and
`down`/`up`. It is lost with `docker compose down -v` (which also deletes the database); deploy it
again after that.

### 2. Deploy the theme

```bash
cd plugins/theme/iiitdwd
npm install       # first time only (dev tooling: stylelint)
npm run lint      # optional: lint the SCSS
npm run check     # compile the SCSS with Moodle's compiler, installs nothing
npm run deploy    # copy into moodle_app, run the Moodle upgrade, purge caches
```

Without Node.js, run the same steps by hand from the repository root:

```bash
# Copy the plugin (without the dev tooling) into the container.
docker exec moodle_app rm -rf /bitnami/moodle/theme/iiitdwd
docker exec moodle_app mkdir -p /bitnami/moodle/theme/iiitdwd
tar -C plugins/theme/iiitdwd --exclude=node_modules --exclude=tools --exclude=package.json \
    --exclude=package-lock.json --exclude=.stylelintrc.json --exclude=README.md -cf - . \
  | docker exec -i moodle_app tar -C /bitnami/moodle/theme/iiitdwd -xf -

# File permissions: owned by the web server user (daemon), directories 755, files 644.
docker exec moodle_app chown -R daemon:daemon /bitnami/moodle/theme/iiitdwd
docker exec moodle_app find /bitnami/moodle/theme/iiitdwd -type d -exec chmod 755 {} +
docker exec moodle_app find /bitnami/moodle/theme/iiitdwd -type f -exec chmod 644 {} +

# Register a new or bumped version, then purge caches (recompiles the theme CSS).
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/upgrade.php --non-interactive
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/purge_caches.php
```

Inside the container (`docker exec -it -u daemon moodle_app bash`, then `cd /bitnami/moodle`) the last two
are `php admin/cli/upgrade.php --non-interactive` and `php admin/cli/purge_caches.php`.

### 3. Choose who gets the theme

**Option A: site default (every user).** Either set it in the database:

```bash
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/cfg.php --name=theme --set=iiitdwd
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/purge_caches.php
```

(the same as Site administration → Appearance → Themes → IIIT Dharwad → **Use theme**), or force it in
`/bitnami/moodle/config.php`, above the `require_once(__DIR__ . '/lib/setup.php');` line:

```php
$CFG->theme = 'iiitdwd';
```

A theme forced in `config.php` cannot be changed from the admin pages until the line is removed. The file
is owned by root (`root:daemon`, 644), so edit it as root, keep that ownership, then purge caches. Users
who chose a different preferred theme (Option B) keep it while user themes are allowed.

**Option B: per user (site default unchanged).** Enable user themes (Site administration → Appearance →
Themes → Theme settings → Allow user themes):

```bash
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/cfg.php --name=allowuserthemes --set=1
```

Each user then selects it:

1. User menu (top right) → **Profile** → **Edit profile** (or open `https://192.168.30.239/user/edit.php`).
2. **Preferred theme** → **IIIT Dharwad**.
3. **Update profile**. The theme applies on the next page; choosing **Default** goes back to the site theme.

An admin can set it for another user from Site administration → Users → Browse list of users → user
→ Edit profile → Preferred theme.

### 4. Verify the deployment

```bash
# Site theme (Option A) and the registered plugin version (from version.php).
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/cfg.php --name=theme
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/cfg.php --component=theme_iiitdwd --name=version

# Permissions: no output means everything is daemon-owned with 755/644.
docker exec moodle_app find /bitnami/moodle/theme/iiitdwd \( -type d ! -perm 755 \) -o \( -type f ! -perm 644 \) -o ! -user daemon

# moodledata must stay daemon-owned (root-owned files there cause HTTP 500): expect 0.
docker exec moodle_app find /bitnami/moodledata ! -user daemon | wc -l

# Site up, and serving this theme's CSS: expect 200, a .../styles.php/iiitdwd/... URL, then --iiitdwd-bg.
CA=tls/public/iiitdwd-lms-ca.crt
curl -s --cacert $CA -o /dev/null -w "%{http_code}\n" https://192.168.30.239/login/index.php
css=$(curl -s --cacert $CA https://192.168.30.239/login/index.php | grep -o 'https://192.168.30.239/theme/styles.php/[^"]*' | head -1)
echo "$css"; curl -s --cacert $CA "$css" | grep -o -- '--iiitdwd-bg' | head -1
```

The CSS check reflects the site default theme (the login page is anonymous). With Option B, check a page
while logged in as a user who chose IIIT Dharwad instead.

### 5. Test

Log in at https://192.168.30.239 as `admin` (password: `MOODLE_ADMIN_PASSWORD` in `.env`) and check:

| Page | URL | Expect |
|---|---|---|
| App shell | any page, e.g. `/my/` | Left sidebar with the logo and Dashboard / My courses / Calendar (current one highlighted); top bar without Home / Site administration; gear **Manage** menu at the top right for admins (Site administration + shortcuts), absent for other users |
| Dashboard | `/my/` | Navy page (`#050c18`), Timeline and Calendar in dark cards with rounded corners |
| My courses | `/my/courses.php` | Course cards on `#0c182b` with `#132742` borders; course name in blue, category in grey-blue |
| A course | `/course/view.php?id=2` | Left course index drawer on `#031633`, current item highlighted blue; section headers with dark-blue chevrons |
| Forms | any settings page, e.g. `/user/edit.php` | Dark inputs, white text, blue focus ring; dropdowns and modals dark |
| Teacher dashboard | `/my/` as a user who teaches a course (e.g. `admin` in COURSE -01) | Above Timeline: hero with date, greeting and Grade now / Message students; This week tiles and bar chart; 4 metric cards; Needs your attention and Today's live classes. Users who teach nothing see the normal dashboard |
| Your courses | bottom of `/my/` as a teacher | Course cards with a blue (then green) header and code pill, title, student count, completion bar, and Grade / Content / People buttons |
| Course page | `/course/view.php?id=2` | Navigation as a left sub-sidebar; hero with course code, title and dates; Live Session Stats card on the right (empty state until Zoom reports a meeting); sections as rounded dark cards |
| Other course pages | `/user/index.php?id=2` | Same left sub-sidebar with Participants active; no hero |
| Calendar | `/calendar/view.php?view=month` | Filter bar with Month / course / "+ New event"; dark grid with time-stamped coloured event pills; Events key panel on the right; "Join" only on Zoom events (try `?view=month&course=5`) |
| Error page | `/course/view.php` (no id) | Styled error card "This link is incomplete or out of date", no links to docs.moodle.org |
| Notifications | save a form, e.g. Edit profile → Update profile | Success: dark green banner with green left edge; errors red, warnings amber, info blue; light text and close button |
| Forms and editor | `/course/edit.php?id=2` | Dark inputs and selects, readable category pill in the autocomplete; TinyMCE toolbar dark with light icons and a dark editing area |
| Modal | Calendar → "+ New event" | Dark dialog with rounded corners, dark inputs, blurred backdrop |
| Mobile | browser dev tools, ~375px wide | Menu button opens the sidebar over a dimmed page (closes on tap outside or Escape); no white panels |

Hard-refresh (Ctrl+Shift+R) if you still see the old styles.

### 6. After editing the theme

| You changed | Do this |
|---|---|
| `scss/*.scss` | `npm run deploy` (it purges caches, which recompiles the CSS), then hard-refresh |
| `config.php`, `lib.php`, `lang/` | `npm run deploy`. PHP-FPM re-reads changed PHP files within 60 s (`opcache.revalidate_freq`); `docker compose restart moodle` applies them at once |
| `version.php` (bumped version) | `npm run deploy`; its upgrade step registers the new version |

### 7. Roll back

Site default (Option A): set it back with `--name=theme --set=vocab` (or remove the `$CFG->theme` line from
`config.php`), then purge caches.

Per user (Option B): a user goes back by choosing **Default** as their preferred theme. To stop offering
theme choices (users who picked IIIT Dharwad then fall back to the site default):

```bash
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/cfg.php --name=allowuserthemes --set=0
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/purge_caches.php
```

To remove the plugin completely, uninstall it under Site administration → Plugins → Plugins overview
(theme_iiitdwd → Uninstall) before deleting `/bitnami/moodle/theme/iiitdwd`.
