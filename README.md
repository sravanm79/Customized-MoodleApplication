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
