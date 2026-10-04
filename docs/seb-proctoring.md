# Safe Exam Browser + webcam proctoring

How to run quizzes that need both Safe Exam Browser (SEB) and webcam proctoring, without false
“config key” / “Quit SEB” errors and without SEB blocking the camera.

The pieces involved:

| Component | What it does |
| --- | --- |
| `quizaccess_seb` (core) | Builds the `.seb` file students download and checks the SEB config key / browser exam key on quiz pages. |
| `quizaccess_proctoring` | Webcam snapshots during the attempt (`getUserMedia`, uploaded to Moodle's own web service; face matching runs server-side in cron). |
| `quizaccess_examproctor` | Tab-switch / focus / fullscreen detection and auto-submit. Inside SEB it now turns off its fullscreen and window-focus checks, because SEB's kiosk already enforces both. |
| `mod/quiz/accessrule/examproctor/check.php?cmid=N` | Student device pre-check (camera, mic, SEB keys, connection). Linked as **Check my camera and exam browser** on every proctored quiz page. |
| `mod/quiz/accessrule/examproctor/cli/seb_check.php` | Admin troubleshooting script. |

## 1. Why students get false “config key” errors

Moodle checks SEB like this: `sha256(<page URL> + <config key>)` sent by SEB must equal the same hash computed
on the server. The config key is a hash of the whole `.seb` file, and that file contains
`startURL = $CFG->wwwroot/mod/quiz/view.php?id=N`, which Moodle sets in **every** SEB mode (manual,
template and upload).

On this server `config.php` builds `$CFG->wwwroot` from whatever host name the browser sent
(`'http://' . $_SERVER['HTTP_HOST']`). Moodle caches **one** `.seb` file and **one** config key per quiz,
built for the host of whoever triggered it first. Anyone reaching the site under another address
(`localhost:9999` vs `192.168.30.239:9999` vs a DNS name) gets a `.seb` file that starts on the wrong host,
and their key never matches. That is the false positive.

**Fixed on 2026-10-04:** `$CFG->wwwroot = 'https://192.168.30.239';` with a certificate from the private
"IIIT Dharwad LMS Local CA" (see [https-and-student-devices.md](https-and-student-devices.md)). Every student device
must install that CA once, otherwise neither the browser nor Safe Exam Browser trusts the site. After any later change
of address or SEB settings, rebuild and verify:

```bash
docker exec -u daemon moodle_app php /bitnami/moodle/mod/quiz/accessrule/examproctor/cli/seb_check.php \
    --all --purge --url=https://192.168.30.239
```

Students delete any old `.seb` files and launch SEB again from the quiz page.

Other causes of genuine mismatches (the pre-check page and the CLI both point at these):

- The teacher changed SEB settings after students opened SEB. Keep **Auto-configure SEB**
  (`quizaccess_seb | autoreconfigureseb`) on so the quiz page reloads the new configuration.
- A student double-clicked an old `.seb` file from Downloads instead of using **Launch Safe Exam Browser**.
- **Upload mode with browser exam keys:** the key changes with every SEB version and build. After students
  update SEB, add the new key, or use the config key alone.
- Very old SEB versions that do not expose `window.SafeExamBrowser` to the page. Update to the current release.

## 2. Settings checklist

### Server (admin)

- [x] `$CFG->wwwroot` fixed to the exam address (section 1); `seb_check.php` shows no `ERROR`.
- [x] Site served over **HTTPS** (needed for the camera on any device other than the server itself).
- [ ] Every exam device has the LMS CA installed ([https-and-student-devices.md](https-and-student-devices.md)).
- [ ] **Auto-configure SEB** (`autoreconfigureseb`) on (Site administration › Plugins › Activity modules › Quiz › Safe Exam Browser).
- [ ] Cron running (proctoring face matching and quiz tasks).
- [ ] No firewall or proxy blocks `/webservice/` and `/lib/ajax/service.php` (camera snapshots and SEB key
      checks go there).

### Quiz (teacher): Safe Exam Browser section

- [ ] **Require the use of Safe Exam Browser:** *Yes – Configure manually* (simplest) or *Yes – Use an existing
      template* built from the XML below.
- [ ] **Allow browser access to camera:** Yes (`browserMediaCaptureCamera`).
- [ ] **Allow browser access to microphone:** Yes only if the proctoring tool records audio (`browserMediaCaptureMicrophone`).
- [ ] **Show reload button** and **Enable reload in exam:** Yes, so a student can recover a frozen camera
      without quitting.
- [ ] **Enable quitting of SEB:** Yes, with a **quit password** that the invigilator knows. Otherwise a
      student with a wrong configuration is stuck.
- [ ] **Enable URL filtering:** optional. If on, **Expressions allowed** must contain the exam host,
      e.g. `192.168.30.239/*`.
- [ ] **Filter also embedded content:** No, unless every embedded host is also allowed.
- [ ] Do not upload browser exam keys unless every student uses the same SEB build.

### Quiz (teacher): proctoring sections

- [ ] **Webcam proctoring** (`quizaccess_proctoring`): enabled if you need snapshots.
- [ ] **Proctored exam mode** (`quizaccess_examproctor`): enabled. *Require fullscreen* and *Detect
      switching to other applications* apply only outside SEB; inside SEB they turn off automatically.

### Student (before the exam)

- [ ] Open the quiz and click **Check my camera and exam browser** in a normal browser: camera picture
      visible, summary not “Not ready”.
- [ ] Click **Launch Safe Exam Browser**, then open the same check again inside SEB: “Running inside Safe
      Exam Browser”, “configuration accepted by Moodle”, camera OK.
- [ ] macOS: allow *Safe Exam Browser* in System Settings › Privacy & Security › Camera.
- [ ] Close Zoom, Teams, Meet and other apps that hold the camera.

## 3. SEB key names

The settings often quoted as `allowmediaaccess`, `allowmicrophone` and `allowcameramedia` are not SEB keys,
and SEB ignores them. The keys that SEB and Moodle use are:

| Purpose | `.seb` key | Moodle quiz setting |
| --- | --- | --- |
| Camera via `getUserMedia` | `browserMediaCaptureCamera` | Allow browser access to camera |
| Microphone | `browserMediaCaptureMicrophone` | Allow browser access to microphone |
| Allow-list of sites | `URLFilterEnable` + `URLFilterRules` | Enable URL filtering + Expressions/Regex allowed |
| Filter embedded resources too | `URLFilterEnableContentFilter` | Filter also embedded content |
| Reload | `browserWindowAllowReload`, `showReloadButton` | Enable reload in exam, Show reload button |
| Quit | `allowQuit`, `hashedQuitPassword`, `quitURL` | Enable quitting of SEB, Quit password, Show Exit Safe Exam Browser button |
| Set by Moodle, do not edit | `startURL`, `sendBrowserExamKey`, `browserWindowWebView` | — |

## 4. Configuration XML template

[`seb-proctoring-template.seb.xml`](seb-proctoring-template.seb.xml) contains exactly the keys Moodle's
manual mode generates, with the camera and microphone on and URL filtering allowing only the exam host.
Use it for *Yes – Use an existing template* (Site administration › Plugins › Quiz › Safe Exam Browser templates)
or *Upload my own config*:

1. Replace both `EXAM_HOST` with the host from `$CFG->wwwroot` (no `https://` in the filter rule).
2. Leave `startURL` as it is. Moodle overwrites it with the quiz URL.
3. In template mode, Moodle adds the quit password and quit link from the quiz form. In upload mode, set
   them in SEB Config Tool before uploading.
4. Do not hand-edit the `.seb` file Moodle produces in manual mode. Any change alters the config key, and
   students get exactly the mismatch this guide is about.

To see the exact file Moodle serves for a quiz:

```bash
docker exec -u daemon moodle_app php /bitnami/moodle/mod/quiz/accessrule/examproctor/cli/seb_check.php \
    --cmid=10 --url=https://192.168.30.239 --xml
```

### WebRTC and third-party proctoring services

The installed proctoring plugins do not use WebRTC. The camera stream stays in the page, and snapshots are
posted to Moodle's own web service, so the only host SEB must allow is Moodle itself. If you add an
external live-proctoring service that streams over WebRTC:

- Add allow rules for each of its web, API and signalling hosts. Use regex rules for `wss://`, e.g.
  `^wss://signal\.vendor\.com/.*`, and keep *Filter also embedded content* off or list those hosts too.
- Media relays (STUN/TURN, usually UDP 3478 / TCP 443) are not HTTP, so SEB's URL filter does not touch
  them. The campus firewall must allow them.
- Browser-based **screen** sharing (`getDisplayMedia`) is not available inside SEB. For screen recording use
  SEB Server's screen proctoring instead of a web page.

## 5. Troubleshooting

| What the student sees | Cause | Fix |
| --- | --- | --- |
| “The config key or browser exam keys could not be validated” right after launching | `.seb` built for a different address (dynamic wwwroot), or stale `.seb` file | Section 1; student relaunches from the quiz page |
| SEB opens a page that does not load (`127.0.0.1:8080`, `localhost`) | Same: the cached `.seb` file's `startURL` is the wrong host | Fix wwwroot, `seb_check.php --purge` |
| Key error only after the teacher edited the quiz | Config changed; student's SEB holds the old one | Keep auto-reconfigure on; student quits and relaunches |
| Key error only on some laptops (upload mode) | Browser exam key differs per SEB version | Add their key or rely on the config key |
| Pre-check inside SEB: “Safe Exam Browser blocked the camera” | `browserMediaCaptureCamera` off, or macOS privacy permission missing for SEB | Turn on “Allow browser access to camera” in the quiz SEB settings; grant macOS permission |
| Camera works in Chrome but not in SEB on http | Insecure context. SEB applies the same rule as browsers. | Serve the site over HTTPS |
| “Camera is in use by another application” | Zoom/Teams/Meet hold the device | Close them; reboot if needed |
| Picture is black | Privacy shutter or covered lens | Open shutter, add light |
| Proctored exam keeps showing “Fullscreen required” in SEB | Old `quizaccess_examproctor` (< 0.2.0) | Upgrade the plugin; inside SEB the gate is now skipped |
| Violations counted when SEB shows its own dialogue | Same: old plugin version counted window blur | Upgrade the plugin |
| Student cannot leave SEB after a failed check | `allowQuit` off or unknown quit password | Invigilator enters the quit password; enable quitting for future exams |

### Admin script

```bash
# One quiz, as the students reach it:
docker exec -u daemon moodle_app php /bitnami/moodle/mod/quiz/accessrule/examproctor/cli/seb_check.php \
    --cmid=10 --url=https://192.168.30.239
# Every SEB quiz, after changing wwwroot or SEB settings:
docker exec -u daemon moodle_app php /bitnami/moodle/mod/quiz/accessrule/examproctor/cli/seb_check.php --all --purge
```

Exit code 0 = OK, 1 = warnings, 2 = errors. It reports the SEB mode, whether wwwroot is fixed, HTTPS,
whether the `.seb` `startURL` matches the students' address, camera and microphone flags against the
proctoring settings, URL-filter rules, and auto-reconfigure. It prints the config key and the expected
page hash for `view.php`. While wwwroot is still dynamic it purges the SEB caches it touched, so it never
leaves a CLI-host `.seb` file cached for students.

### Student report

The pre-check page has a **Diagnostic report** box (browser, SEB version, each check and its result, no
passwords or answers). Students copy it into a support ticket to `support.dsai@iiitdwd.ac.in`.
