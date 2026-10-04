# local_studentportal

Student onboarding and the student's own views, styled by `theme_iiitdwd`.

## For admins and managers: register students and share logins

*Manage › Register students*, *Site administration › Users › Accounts › Register students*, or a course's
**Students and logins** page (course sub-sidebar) › **Register students**.

1. **Courses**: one or more; students are enrolled as *student* in each.
2. **Students**: one (roll number, first name, last name, email) or a list: a CSV file and/or pasted rows
   `Roll number,First name,Last name,Email` (header optional, any column order with a header; `,` `;` or tab).
3. **Preview**: every row shows *New account*, *Existing account* (enrol only), *No change* or *Problem*
   (invalid email, missing name, duplicate row, username taken, ambiguous/conflicting match, suspended or admin account).
4. **Confirm**: accounts are created (username = roll number in lower case, else the email before "@"; ID number =
   roll number), with a generated password that meets the site policy and **must be changed at first login**.
5. **Share** (the passwords exist only in your session for 15 minutes and are never stored readable):
   **Download CSV**, **Print slips** (one per student, with the address, certificate link and first-login steps),
   **Email each student** (own address only; via Mailpit, see `docs/email.md`), **Copy all**. Then **Done**.

A course's **Students and logins** page lists its students, highlights who has **never logged in**, and resets
passwords (one row, or the ticked rows) to share them again.

Capability `local/studentportal:register` (system; managers by default; admins always), plus
`moodle/user:create` and `enrol/manual:enrol`.

## For students

- **Dashboard** (`/my/`, rendered by `theme_iiitdwd` for users whose role is Student): greeting and summary,
  this week's activity, metrics (courses, average grade, due this week, completion), **Up next** (assignments to
  submit, tests to take, next 30 days, from the calendar) with action buttons, **Live classes** (Zoom, next 7 days)
  with **Join**, **Recent grades & feedback**, and course cards with progress and grade.
- **My performance** (`/local/studentportal/performance.php`, sidebar): metrics, 30-day activity chart, insights
  (best result, what needs work, courses not opened for a week), and per course the course grade, completion and
  every graded item with its grade and feedback (teacher or released LLM feedback).

Only the student's own data, and only what the gradebook lets them see (hidden items, hidden grades and grades not
yet released are left out).

## Privacy

Stores nothing of its own (null privacy provider).
