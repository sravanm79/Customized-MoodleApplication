# Manual testing guide: IIIT Dharwad LMS

Step-by-step checks for every feature built so far. Work top to bottom: later sections reuse what earlier ones set
up (the certificate, the logo, a submission waiting to be graded). Tick each result and note anything odd in the
**Notes** line; one failed check should not stop you from doing the rest.

Every test was run automatically on 2026-10-04 and passed. This guide is for a person to confirm what users actually
see, on real devices, including the things automation cannot check (a real webcam, Safe Exam Browser, a phone, a
real Zoom meeting).

---

## 0. Before you start

### 0.1 What you need

- [ ] A computer on the campus network, with Chrome or Edge, a **webcam** and a microphone.
- [ ] A second browser or a private/incognito window (to be teacher and student at the same time).
- [ ] A phone on the campus Wi-Fi (for the mobile checks).
- [ ] **Safe Exam Browser** 3.x installed on one Windows or macOS computer (for section 13).
- [ ] The sample files in [`docs/test-data/`](test-data/).
- [ ] The IIIT Dharwad logo as a PNG or SVG file.

### 0.2 Addresses

| What | Address |
| --- | --- |
| LMS | `https://192.168.30.239` |
| LMS certificate | `http://192.168.30.239:9999/lms-ca.crt` |
| Mail inbox (Mailpit) | `https://192.168.30.239/mail/` |

### 0.3 Accounts

| Role | Username | Where to find the password | Course |
| --- | --- | --- | --- |
| Site admin | `admin` | `MOODLE_ADMIN_PASSWORD` in `.env` on the server (**changed on 2026-10-04**, the old one no longer works) | Teacher in "Python course" |
| Teacher | `teacher` (Arcyabhatta chary) | the password you set when you created it | Teacher in **PYTHON LESSON** (`COURSE -01`) |
| Zoom teacher | `zoomteacher` (Kashyap S) | the password you set | Teacher in **Zoom Test** (hidden course) |
| Students | `s1`, `u2`, `u3` | the passwords you set | Students in **PYTHON LESSON** |
| Mail inbox | `admin` | `MAILPIT_UI_PASSWORD` in `.env` | |

If you don't know a test user's password, log in as `admin`, open the user's profile
(*Site administration › Users › Browse list of users* › the user) and click **Log in as**. Log out to return.
(Section 2.4 also tests the forgotten-password email.)

### 0.4 Where things are (PYTHON LESSON, course id 2)

| Activity | URL |
| --- | --- |
| Assignment BASICS-01 | `/mod/assign/view.php?id=3` |
| Assignment AUTO GRADER TEST (LLM) | `/mod/assign/view.php?id=16` |
| Quiz PYTHON TEST (Safe Exam Browser + proctoring, has a quiz password) | `/mod/quiz/view.php?id=10` |
| Quiz CODING TEST (CodeRunner) | `/mod/quiz/view.php?id=15` |
| Jupyter activity | `/mod/jupyter/view.php?id=13` |
| YouTube video | `/mod/url/view.php?id=8` |
| Proctored Exam Demo quiz (course "Proctoring Demo", hidden) | `/mod/quiz/view.php?id=4` |
| Zoom classes (course "Zoom Test", hidden) | `/mod/zoom/view.php?id=24`, `?id=26` |

---

## 1. Certificate and address

### 1.1 Install the LMS certificate on your computer
1. Open `http://192.168.30.239:9999/lms-ca.crt`; the file `iiitdwd-lms-ca.crt` downloads.
2. Install it as a trusted root CA, following the table in [https-and-student-devices.md](https-and-student-devices.md).
3. Optional: check its SHA-256 fingerprint is `28:72:CA:48:…:66:0E:51` (full value in that guide).

- [ ] Expected: the install succeeds on Windows/macOS/Linux as described.

### 1.2 Padlock
1. Open `https://192.168.30.239`.

- [ ] Expected: the login page loads with a normal padlock and **no certificate warning**.
- [ ] Click the padlock › certificate: issued to `192.168.30.239`, by "IIIT Dharwad LMS Local CA", valid until Nov 2027.

### 1.3 Old addresses redirect
1. On the server, open `http://localhost:9999`.
2. From any computer, open `http://192.168.30.239:9999/course/view.php?id=2`.

- [ ] Expected: both end at `https://192.168.30.239/...` (the login page or home).

### 1.4 Without the certificate
1. On a device **without** the certificate (or a phone before installing it), open `https://192.168.30.239`.

- [ ] Expected: the browser warns that the connection is not private. This is why every exam device needs the
      certificate. Install it on the phone now (Android/iPhone steps in the guide) and reload: no warning.

Notes: ______________________________________________

---

## 2. Login page and accounts

### 2.1 Login page design (logged out)
Open `https://192.168.30.239/login/index.php` in a private window.

- [ ] Dark navy background; the form sits on a darker card.
- [ ] Institution name/logo centred above the form. Until the logo is uploaded (3.1), the text
      "INDIAN INSTITUTE OF INFORMATION TECHNOLOGY" shows instead.
- [ ] Username and password fields are rounded (pill-shaped) with a subtle border; the focused field gets a blue ring.
- [ ] The **Log in** button is blue and as wide as the form.
- [ ] Link **Forgotten your username or password?**
- [ ] Support contact at the bottom: `support.dsai@iiitdwd.ac.in` (clicking opens a new email).

### 2.2 Login errors
1. Log in with a wrong password.

- [ ] Expected: a red "Invalid login" message; the page stays styled.

### 2.3 Login works for each role
- [ ] `admin` (password from `.env`) → Dashboard.
- [ ] `teacher` → Dashboard.
- [ ] `s1` → Dashboard.

### 2.4 Forgotten password email
1. Logged out, click **Forgotten your username or password?** → enter username `s1` → **Search**.
2. Open `https://192.168.30.239/mail/` (user `admin`, `MAILPIT_UI_PASSWORD`).

- [ ] The page says an email was sent.
- [ ] Mailpit shows "Password reset request" to `s1@gmail.com`, from `noreply@iiitdwd.ac.in`.
- [ ] The link in the email starts with `https://192.168.30.239/login/forgot_password.php?token=`; opening it shows
      the **set new password** form. (You may set a new password for `s1` here; note it down.)

### 2.5 Mobile login
On the phone, open the login page.

- [ ] Everything fits the screen; no sideways scrolling; button and fields are easy to tap.

Notes: ______________________________________________

---

## 3. Theme, branding and layout (`theme_iiitdwd`)

### 3.1 Upload the logo (admin)
1. *Site administration › Appearance › Themes › IIIT Dharwad*
   (or `https://192.168.30.239/admin/settings.php?section=themesettingiiitdwd`).
2. **Institution logo**: add your logo file. **Login page support email**: leave `support.dsai@iiitdwd.ac.in`.
3. **Save changes**.

- [ ] The settings page opens (this was broken before 2026-10-04: the page was hidden).
- [ ] "Changes saved".
- [ ] The logo shows at the top of the left sidebar on every page, and centred on the login page (private window).
- [ ] Changing the support email and saving changes the address on the login page (then set it back).

### 3.2 App shell (as admin, teacher and s1)
On `/my/` (Dashboard):

- [ ] Left sidebar: logo, then a **role pill** (admin and teacher: "TEACHER"; s1: "STUDENT"), then Dashboard /
      My courses / Calendar with the current page highlighted.
- [ ] A role badge next to the page title "Dashboard" (Teacher / Student), matching the pill.
- [ ] The page fills the width beside the sidebar; not a narrow column in the middle.
- [ ] Hovering over sidebar links, buttons and cards gives a subtle highlight; spacing looks even.
- [ ] Top bar: sun/moon toggle **next to the notification bell**, the bell, the chat icon, the user menu.

### 3.3 Manage menu
- [ ] As **admin**: a gear **Manage** menu at the top right with Site administration, Users, Courses and categories,
      Plugins, Themes, Notifications and upgrades, Purge caches; every item opens its page.
- [ ] As **teacher** and **s1**: no Manage menu.

### 3.4 Day/Night mode
1. Click the sun/moon toggle.

- [ ] Instantly switches between dark (navy) and light; text stays readable in both, on Dashboard, a course page,
      the calendar and a form.
- [ ] Go to another page: same mode.
- [ ] Log out and log in again (or another browser with the same user): same mode (saved in the profile).
- [ ] Each user has their own choice (teacher dark, s1 light, at the same time).

### 3.5 Responsive (phone or browser dev tools ~375 px)
Check Dashboard, a course page, the calendar, a quiz page and an assignment page:

- [ ] No sideways scrolling.
- [ ] The menu button opens the sidebar over a dimmed page; tapping outside or pressing Escape closes it.

### 3.6 Theme choice per user (#147: iiitdwd must be optional)
1. As s1: user menu › **Preferences** › **Preferred theme** (or *Edit profile*) › choose **Vocab** › save.

- [ ] The whole site switches to the Vocab look: no IIIT Dharwad sidebar, badges or day/night toggle.
- [ ] Choose **Boost** → plain Moodle look. Choose **IIIT Dharwad** → back. Choose **Default** → the site default.

### 3.7 Site default theme (admin)
*Site administration › Appearance › Themes* (Theme selector).

- [ ] Shows which theme is the site default (currently **IIIT Dharwad**). Change it if you want the default to be
      Vocab; users who picked a theme keep theirs.

Notes: ______________________________________________

---

## 4. Courses and enrolment

### 4.1 Enrolled students see the course (#155)
1. As teacher: PYTHON LESSON › **Participants**: s1, u2, u3 listed as students.
2. As s1: **My courses**.

- [ ] PYTHON LESSON is listed for s1 (and for u2, u3).
- [ ] Enrol a student (teacher: Participants › **Enrol users**), then log in as that student: the course appears.
      Unenrol again afterwards if it was only a test.

### 4.2 Course page and vertical sub-sidebar (#166)
As teacher, open PYTHON LESSON.

- [ ] The course navigation (Course, Settings, Participants, Grades, Reports, Question banks, More…) is a
      **vertical list on the left**, not tabs across the top.
- [ ] The current item is a **blue pill**; items have icons; groups (e.g. Reports) expand to sub-items.
- [ ] Open every item at least once (Settings, Participants, Enrolment methods, Groups, Permissions, Grades,
      Gradebook setup, Reports › Logs/Live logs/Activity report/Course participation, Question banks, Content bank,
      Course completion, Badges, Competencies, Filters, CodeRunner management, LTI External tools, Recycle bin,
      **Grade sheets**, Course reuse › Import/Backup/Restore/Reset): each opens without an error, with the correct
      item highlighted.
- [ ] As s1 the list is shorter (no Settings, Participants management, Reports…).

Notes: ______________________________________________

---

## 5. Messaging drawer (#167)

1. As s1: chat icon (top bar) → search "Arcyabhatta" → open the conversation → send "Hello teacher".
2. In the other browser as teacher: look at the chat icon.

- [ ] A red counter "1" on the teacher's chat icon.
- [ ] Clicking it slides the messaging drawer in **from the right**, dark styled.
- [ ] The conversation list shows s1 with the message preview; opening it shows chat bubbles: received on the left
      (dark), sent on the right (blue), times readable.
- [ ] The teacher replies → s1's counter shows 1 → reading it clears the counter.
- [ ] Starring, the search box and the settings (gear) inside the drawer work.

Notes: ______________________________________________

---

## 6. Calendar and Zoom (#30, #35, #168)

### 6.1 Calendar month view
As **zoomteacher**: Calendar › **Month**, October 2026.

- [ ] Dark day cells; today has a highlighted badge; events are coloured pills with times.
- [ ] "Python Class - Weekly (Mon/Wed)" on Mondays/Wednesdays at 18:00 with a small **Join** chip; the past
      class on 2 October has no Join chip.
- [ ] Events key on the right; filters (Month, course, **+ New event**) work.

### 6.2 Event popup
Click a weekly class event.

- [ ] Shows the time, the course name ("Zoom Test") and the event type.
- [ ] A **Join Zoom meeting** button.

### 6.3 One-click join (needs a real meeting time)
Within 15 minutes before a class (or create a Zoom activity that starts in 10 minutes: Zoom Test ›
*Add an activity › Zoom meeting*):

- [ ] Clicking **Join Zoom meeting** opens Zoom in a new tab **without asking for a meeting ID or passcode**
      (the teacher gets the host start link; a student gets the join link with the passcode).
- [ ] Before the join window, the button leads to the activity page saying the meeting has not started.

### 6.4 Dashboard
As zoomteacher: Dashboard.

- [ ] The calendar block and "Today's live classes" show the Zoom classes with Join links.

Notes: ______________________________________________

---

## 7. Teacher dashboard: "Needs your attention" (#169)

### 7.1 Empty state
As teacher, Dashboard.

- [ ] Card **Needs your attention** shows "All caught up!" (nothing to grade yet).

### 7.2 Item to grade
1. As s1: BASICS-01 › **Add submission** › upload any `.ipynb` (e.g. `docs/test-data/notebook-all-outputs.ipynb`) ›
   **Save changes** › **Submit assignment** (if asked).
2. As teacher: Dashboard.

- [ ] An item with a red alert icon, the assignment name, the course (COURSE -01), a badge "1 to grade" and an
      arrow button.
- [ ] The arrow opens the grading page for that assignment.
- [ ] Grade s1 (any mark) and save; back on the Dashboard the item is gone → "All caught up!".
- [ ] As s1: the Dashboard has **no** "Needs your attention" card.

Notes: ______________________________________________

---

## 8. Jupyter notebook viewer (#170)

### 8.1 Teacher opens a submission
As teacher: AUTO GRADER TEST › **View all submissions** → click `answers_2.ipynb` (u2's file).

- [ ] Opens **on screen in a dialog**; nothing is downloaded.
- [ ] Code cells have dark backgrounds with coloured Python syntax; markdown cells show headings, bold text, lists.
- [ ] **Download original .ipynb** in the dialog header downloads the file, which opens in Jupyter.

### 8.2 Outputs
Open s1's submission from 7.2 (`notebook-all-outputs.ipynb`) the same way.

- [ ] "Outputs test" heading, bold text, and the list **one / two shown as a list** (fixed 2026-10-04).
- [ ] Printed output "hello 9", a **line chart image**, a **table** (pandas), and a red error "ZeroDivisionError".
- [ ] No alert box pops up (the sample contains a script tag; it must be removed, not run).

### 8.3 Student
As s1: BASICS-01 → click the attached `Python_Basics_Assignment.ipynb`.

- [ ] Opens in the viewer as well.

Notes: ______________________________________________

---

## 9. Grade sheets (#171)

### 9.1 Upload with problems (teacher)
PYTHON LESSON › course navigation **Grade sheets** › **Upload grade sheet**; name "Test sheet", maximum 100, file
`docs/test-data/gradesheet-mixed.csv` › **Preview**.

- [ ] "8 rows read, 3 ready to import, 5 with problems".
- [ ] Skipped rows: `ghost` and `teacher` "Not a student of this course"; repeated students "already has a score".
- [ ] The column choosers read **Student ID column** and **Score column** (no `[[scorecolumn]]`).
- [ ] Cancel.

### 9.2 Bad values
Upload `gradesheet-bad-values.csv` › Preview.

- [ ] `abc` "Score is not a number"; `150` and `-5` "below 0 or above the maximum". Cancel.

### 9.3 Excel import
Upload `gradesheet-valid.xlsx` (name "Excel test", max 100) › Preview → **Import 3 scores**.

- [ ] "Imported 3 scores … They are also in the gradebook."
- [ ] Teacher view: average **79%**, median **80%**, lower quartile **72.5%**, upper quartile **86%**, lowest 65%,
      highest 92%, a distribution chart, and **Show all 3 scores** lists each student.
- [ ] Gradebook (Grades) has a new column "Excel test" with 80 / 65 / 92.

### 9.4 Student view
As s1, then u2: course navigation **Grade sheets**.

- [ ] s1 sees **80%, 80 out of 100**; u2 sees 65%. Nobody sees another student's score or name.
- [ ] Below 5 students with scores: "Class statistics appear once at least 5 students have a score" (privacy).
- [ ] Optional: as admin set *Site administration › Plugins › Local plugins › Grade sheets › Minimum class size for statistics*
      to **3**; then u2 sees "You scored higher than **0%** of your classmates", s1 "**50%**", u3 "**100%**", and the
      class average/median/quartiles and distribution with "(your score)" marked. Set it back to **5**.

### 9.5 Delete
Teacher: **Delete** on the sheet → confirm.

- [ ] Gone from the list and from the gradebook.

Notes: ______________________________________________

---

## 10. LLM auto-grading (#54, #172)

### 10.1 Settings
As teacher: AUTO GRADER TEST › assignment menu (**More**) › **Auto Grade** (or
`/local/llmgrader/report.php?id=16`) › **LLM settings**.

- [ ] Fields: **When to run** (when a student submits / when submissions close / only when a teacher clicks
      "Assign to LLM"), **Teacher review**, **Rubric and criteria**, **Reference solution**, **Guidelines**.
- [ ] Enter a short rubric (e.g. "Task 1 (5 marks): variables. Task 2 (5 marks): loops.") and a reference solution;
      choose "only when a teacher clicks"; Save.
- [ ] The report header shows "Rubric: Yes · Reference solution: Yes".

### 10.2 Run
1. Make sure a student has a submitted notebook (as u3: submit `docs/test-data/notebook-all-outputs.ipynb` to
   AUTO GRADER TEST).
2. Teacher: report › **Assign to LLM** (or **Evaluate again** on a row).

- [ ] "1 submission(s) sent to the LLM"; within about a minute (reload) the row shows **Draft: awaiting review**
      with a suggested grade, marks per rubric criterion, token count and time.
- [ ] As that student: the assignment page shows **no** grade or feedback yet.

### 10.3 Review and release
Teacher: open the draft.

- [ ] Per-criterion table (marks and comments), the grade and the feedback in an editor, buttons **Approve and
      release to student**, **Reject**, **Cancel**.
- [ ] Edit the feedback a little → **Approve and release** → "Approved: the grade and feedback were released".
- [ ] As the student: grade and the (edited) feedback are now visible; the gradebook shows the grade.
- [ ] **Reject** on another draft: status Rejected, the student sees nothing.

### 10.4 Automatic triggers (optional)
- [ ] "When a student submits": a new submission becomes a draft without clicking anything.
- [ ] "When submissions close": after the due/cut-off date passes, drafts appear within 15 minutes.

Notes: ______________________________________________

---

## 11. Jupyter in Moodle (#43, isolation #44)

### 11.1 Student notebook
As s1: Jupyter activity `/mod/jupyter/view.php?id=13`.

- [ ] JupyterLab opens **inside the Moodle page** (first start may take ~10 s), with the assignment notebook.
- [ ] Run a cell (Shift+Enter), e.g. `import pandas; print(6*7)` → prints 42.
- [ ] Save, leave, come back: the work is still there.
- [ ] Click **Submit notebook** on the activity page: "Submitted on …"; as teacher, the submissions page shows it and it
      opens in the viewer.

### 11.2 Isolation (students cannot see each other)
In s1's notebook run:

```python
import os, getpass
print(getpass.getuser(), os.getuid())
print(os.listdir('/home'))
print(os.listdir('/home/jovyan/work'))
```

- [ ] Prints `jovyan 1000`, only `['jovyan']`, and only s1's own files. No other student's folder exists.
- [ ] `!sudo ls` fails; `import socket; socket.gethostbyname('mariadb')` fails (database not reachable).
- [ ] As u2 open the same activity: u2 gets their own copy; s1's changes are not visible.

### 11.3 Idle shutdown
- [ ] (Server) `docker ps | grep jupyter-` shows one container per active student; after an hour without use it
      disappears; opening the activity again restarts it with the work intact.

Notes: ______________________________________________

---

## 12. CodeRunner quiz (#50)

As s1 (or teacher with **Preview quiz**): CODING TEST › Attempt.

- [ ] The answer box is a code editor (line numbers, syntax colours).
- [ ] Paste `docs/test-data/coderunner-square-wrong.py` → **Check** → a table Test / Expected / Got with red rows,
      "Some hidden test cases failed", mark Incorrect.
- [ ] Paste the correct version → **Check** → all green, **Passed all tests!**, Correct.
- [ ] Finish the attempt; as teacher the quiz Results show the attempt and mark.

Notes: ______________________________________________

---

## 13. Quizzes: proctoring and Safe Exam Browser

### 13.1 Proctored exam mode in a normal browser (#29)
As teacher: Proctoring Demo course › **Proctored Exam Demo** › **Preview quiz** (or temporarily make the course
visible and enrol a student).

- [ ] The quiz page lists the rules (tab switching, leaving the window, fullscreen, copy/paste, auto-submit after
      3 violations) and a button **Check my camera and exam browser**.
- [ ] Start: you must tick "I understand and agree to these exam rules".
- [ ] The exam is hidden behind **Fullscreen required** until you click **Enter fullscreen**.
- [ ] Top bar "Proctored exam … Violations: 0 / 3".
- [ ] Try to copy question text or right-click: blocked with "This action is disabled during the exam".
- [ ] Switch to another tab for a few seconds and come back: "You left the exam … (violation 1)"; the bar shows
      **1 / 3** (one switch = one violation; fixed 2026-10-04) and the exam asks for fullscreen again.
- [ ] Alt+Tab to another application and back: 2 / 3.
- [ ] A third violation submits the attempt automatically ("Violation limit reached"), and you land on the review page.
- [ ] Quiz page › **View proctoring report**: the attempt with counts per event type; clicking it lists each
      event with times.

### 13.2 Webcam proctoring plugin (#157)
As teacher: Proctored Exam Demo › Settings › **Extra restrictions on attempts** › **Webcam identity validation:
Enable webcam capture by Proctoring** › Save. Preview the quiz.

- [ ] Before starting: a camera preview; **Start attempt** stays disabled until the camera works and you tick the
      webcam agreement.
- [ ] During the attempt a small webcam preview is shown; pictures are taken every 30 seconds.
- [ ] Quiz page › **View proctoring report** (the webcam plugin's link, next to the proctored-exam report link): the
      pictures per user and time ("Identity mismatch: Not Found" is expected: no face-matching service is configured).
- [ ] Turn the setting off again if it was only a test.

### 13.3 Device pre-check (#176)
As s1: PYTHON TEST › **Check my camera and exam browser**.

- [ ] Camera: **Start camera** shows your face; "Camera working: <your camera>, 1280x720" (or similar).
      Cover the lens → after restarting the camera: "almost black … open the privacy shutter".
- [ ] **Test microphone**: the bar moves when you speak; "Microphone working".
- [ ] This device: "Secure connection", "Connection to Moodle OK (… ms)".
- [ ] Exam settings: "The site uses HTTPS", "The site address is fixed", "The Safe Exam Browser configuration points
      to this site (https://192.168.30.239)".
- [ ] Summary at the top; **Diagnostic report** › **Copy report** copies the text.
- [ ] With a camera already used by another app (e.g. the Camera app open): a clear "in use by another application"
      message.

### 13.4 Safe Exam Browser (computer with SEB and the LMS certificate installed)
As s1: PYTHON TEST in a normal browser.

- [ ] The quiz page says it needs Safe Exam Browser; **Launch Safe Exam Browser** opens SEB on the quiz page.
- [ ] Inside SEB: **Check my camera and exam browser** → "Running inside Safe Exam Browser (version …)",
      "**Safe Exam Browser configuration accepted by Moodle**", camera OK. **No "config key" / "Quit SEB" error.**
- [ ] Start the attempt (quiz password from the teacher): the proctoring bar shows **"Safe Exam Browser"**, there is
      **no** fullscreen gate, and SEB's own toolbar/dialogs do not count as violations.
- [ ] Copy/paste is still blocked.
- [ ] Quit SEB: with the quit password (if one is set) or the exit button.
- [ ] A device **without** the certificate: SEB cannot load the page. This is why every exam device needs it.

### 13.5 Admin check (server terminal)
```bash
docker exec -u daemon moodle_app php /bitnami/moodle/mod/quiz/accessrule/examproctor/cli/seb_check.php --all --url=https://192.168.30.239
```
- [ ] Ends with `Overall: OK`.

Notes: ______________________________________________

---

## 14. Other content

### 14.1 YouTube video (#37)
As s1: `/mod/url/view.php?id=8` (PYTHON TUTORIAL IN 5 MINS).

- [ ] The video plays **inside Moodle**.
- [ ] Note: it embeds video `DWgzHbglNIo`, not the `kqtD5dpn9C8` link from the original request. Change it in the
      activity settings if that is wrong.

### 14.2 Two-line code question (#41)
Preview PYTHON TEST (teacher) or open the question bank.

- [ ] The question shows
      ```
      x = "Python"
      print(x[1:4])
      ```
      on **two lines** in a code block.

Notes: ______________________________________________

---

## 15. Error pages and search (#175)

As admin, open each and check for a **styled error card inside the theme**, never a jump to `docs.moodle.org`:

- [ ] `/course/view.php` → "This link is incomplete or out of date".
- [ ] `/course/view.php?id=999` → same style.
- [ ] `/mod/quiz/view.php` → same style.
- [ ] `/nonexistent/page.php` → the theme's 404 page.
- [ ] `/course/index.php?categoryid=` and `/course/search.php?search=` → normal pages, no error.
- [ ] Course search for "python" (top of *My courses* or `/course/search.php?q=python`) → results.
- [ ] No "Documentation for this page" links in page footers; help (?) popups have no "More help" link to Moodle docs.

Notes: ______________________________________________

---

## 16. Email (Mailpit)

- [ ] `https://192.168.30.239/mail/` asks for a password; `admin` + `MAILPIT_UI_PASSWORD` opens the inbox.
- [ ] *Site administration › Server › Email › Test outgoing mail configuration* → send to any address → appears in
      Mailpit.
- [ ] A forum post in Announcements (teacher) → notification emails to the students appear in Mailpit (after cron,
      ~1–2 minutes).
- [ ] Real delivery (only after setting up `SMTP_RELAY_*`, see [email.md](email.md)): a test to an `@iiitdwd.ac.in`
      address arrives in that real inbox; mail to `@gmail.com` test accounts stays only in Mailpit.

Notes: ______________________________________________

---

## 17. Permissions and security spot checks

As s1, try to open these directly; each must say you don't have permission (or redirect away):

- [ ] `/local/llmgrader/report.php?id=16`
- [ ] `/local/llmgrader/settings_assign.php?id=16`
- [ ] `/local/gradesheet/upload.php?id=2`
- [ ] `/mod/quiz/accessrule/examproctor/report.php?cmid=10`
- [ ] `/admin/settings.php?section=local_llmgrader`
- [ ] `https://192.168.30.239/mail/` without the Mailpit password → refused.
- [ ] `http://192.168.30.239:9999/jupyter/` (plain http) → 403 Forbidden.
- [ ] The old admin password (before 2026-10-04) no longer logs in.

Notes: ______________________________________________

---

## 18. Server health (terminal on the server)

```bash
cd /media/vocab/ab36a93d-73ed-432d-98d0-e1e6926ff4257/kashyap/LMS/Moodle
docker compose ps                                     # all Up; mailpit "healthy"
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/checks.php            # "OK"
docker exec moodle_app find /bitnami/moodledata ! -user daemon | wc -l               # 0
docker exec moodle_app tail -5 /bitnami/moodledata/moodle-cron.log                   # no new errors
```

- [ ] All as commented.
- [ ] *Site administration › Server › Tasks › Scheduled tasks*: no task with a red "Fail delay".
- [ ] *Site administration › Notifications*: no warnings except ones you already know.

Notes: ______________________________________________

---

## 19. Student portal: registering students and the student view

### 19.1 Register one student (admin)
*Manage* (gear) › **Register students** (or PYTHON LESSON › course sub-sidebar **Students and logins** ›
**Register students**).

- [ ] Courses: choose PYTHON LESSON. Add: **One student**: roll number `TEST001`, your name, an email address you can
      read in Mailpit (e.g. `test001@example.org`) › **Preview**.
- [ ] Preview shows "New account … test001". **Register 1 student(s)**.
- [ ] Credentials page: name, roll number, username `test001`, a temporary password, "New account"; a warning that the
      password is shown only now (15 minutes).

### 19.2 Share the logins
- [ ] **Download CSV**: a file with the username and password opens in Excel.
- [ ] **Print slips**: one slip per student with the address `https://192.168.30.239/login/index.php`, username,
      temporary password and the 3 first-login steps (including the certificate link); **Print** gives a clean
      black-on-white page.
- [ ] **Email each student**: "1 email(s) sent"; Mailpit shows "Your login for …" to the student's address with the
      same details. (Addresses ending in `.invalid` are never emailed by Moodle and are reported as such.)
- [ ] **Copy all** copies a tab-separated list. **Done: forget these passwords** returns to the form; going back to
      the credentials page says the logins are no longer available.

### 19.3 A list of students
Add: **A list of students**; paste:
```
Roll number,First name,Last name,Email
TEST002,Asha,Rao,test002@example.org
TEST003,Ravi,Kumar,test003@example.org
,student,1,s1@gmail.com
TEST004,Bad,Email,not-an-email
TEST005,Dup,Row,test002@example.org
```
- [ ] Preview: 2 new accounts, s1 "No change" (already enrolled), 2 problems ("invalid email address", "Same email
      or roll number as row …"). Confirm registers only the valid ones.
- [ ] The same with a CSV file (save the lines above as `students.csv`).
- [ ] Choose two courses at once: the students are enrolled in both.

### 19.4 First login as the new student
Private window › log in as `test001` with the temporary password.

- [ ] You must choose a new password straight away; after that you land on the Dashboard.
- [ ] Log out and log in with the new password: works. The temporary one no longer does.

### 19.5 Students and logins (admin)
PYTHON LESSON › **Students and logins**.

- [ ] All students listed; students who never logged in show **Never** in red; the counters at the top match.
- [ ] **Reset password & share** on one row (with another row ticked): only that student gets a new temporary
      password, shown on the credentials page; their old password stops working; they must change it at next login.
- [ ] Tick two rows › **Reset selected & share**: both get new passwords.

### 19.6 Student dashboard (as a student, e.g. s1 or u2)
- [ ] Greeting with your name, "Due this week · Live classes today · Courses", buttons **Continue <course>** and
      **My performance**.
- [ ] This week: bars for your own activity per day; "N / 7 active days".
- [ ] Metrics: My courses, Average grade, Due this week, Average completion, each with an icon.
- [ ] **Up next**: BASICS-01 (**Add submission**) and PYTHON TEST (**Attempt quiz now**); due soon in amber, overdue in
      red; the buttons open the activity.
- [ ] **Live classes**: add a Zoom meeting to PYTHON LESSON for later today (teacher) → it appears with **Join**,
      "Upcoming", and "Live now" once it has started; Join opens Zoom.
- [ ] **Recent grades & feedback**: u2 sees AUTO GRADER TEST 0% (red) with the start of the feedback; after the
      teacher grades something new, it appears at the top.
- [ ] Course card: course name, last visit, grade, progress, **Open** and **Grades**.
- [ ] Sidebar: Dashboard, My courses, Calendar, **My performance**; role pill STUDENT. Teachers and admins see the
      teacher dashboard and no My performance item.
- [ ] Phone width: everything stacks; no sideways scrolling.

### 19.7 My performance (student)
Sidebar › **My performance**.

- [ ] Six metrics (courses, average grade, completion, active days, assignments submitted, tests taken).
- [ ] 30-day activity chart (today highlighted) and Insights ("Needs work: …" for a low grade, "Best result: …" only
      for 60% or more, courses not opened for a week, active days).
- [ ] Per course: course grade and completion, every graded item with its grade bar (green ≥ 75%, amber ≥ 50%, red
      below), **Feedback** expands the teacher's / released LLM feedback; **Full grade report** opens Moodle's report.
- [ ] Hide a grade item in the gradebook (teacher): it disappears from the student's page.
- [ ] Only your own data; no other student's name or grade anywhere.

### 19.8 Permissions
- [ ] As a student, `/local/studentportal/register.php`, `/local/studentportal/students.php?id=2` and any
      `/local/studentportal/credentials.php?key=…` are refused.
- [ ] A teacher (not manager/admin) has no **Students and logins** link and cannot open those pages.

Notes: ______________________________________________

## After testing

- Remove test data you created: test student accounts from section 19 (*Site administration › Users › Browse list of users* › delete), test grade sheets (Delete), test submissions (as teacher, *Remove submission*),
  test forum posts, quiz preview attempts (Results › select › Delete), test messages.
- Set any setting you changed for a test back (minimum class size 5, webcam proctoring off on the demo quiz, the
  Zoom test meeting).
- Report failures with the test number (e.g. "13.4, 2nd check"), the user, the URL and a screenshot. For the
  pre-check page, paste its **Diagnostic report**.
