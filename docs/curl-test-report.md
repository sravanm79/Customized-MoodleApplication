# Moodle Web Services curl test report

**Date:** 2026-09-26 · **Moodle:** 5.0.1 (local Docker, `http://localhost:9999`) · **Protocol:** REST, JSON

This test checks, from outside Moodle with plain `curl`, that an external service (e.g. the FastAPI evaluator) can:
read assignments, read submissions, download a notebook, **write a grade (9.5) with a feedback comment**, and read
that grade back.

## 0. Setup performed

Web services were **disabled** on this instance (`enablewebservices = 0`). To run the test, a one-off PHP CLI script
did the following:

| Change | Value |
|---|---|
| `enablewebservices` | `1` |
| `webserviceprotocols` | `rest` |
| External service | "LLM Grader test" (`llmgrader_test`), enabled, **Can download files = yes** |
| Functions in service | `core_webservice_get_site_info`, `mod_assign_get_assignments`, `mod_assign_get_submissions`, `mod_assign_get_submission_status`, `mod_assign_get_grades`, `mod_assign_save_grade`, `mod_assign_list_participants`, `core_files_get_files` |
| Token | Permanent token for user **admin** (id 2), name "llmgrader dev test" |

The same setup can be done in the UI: *Site administration → Server → Web services → Overview*.

> ⚠️ **This is an admin token, for local dev only.** It can do anything the site admin can do through these
> functions. For real use, create a dedicated "LLM Grader" user with a Teacher role, give it its own token with an
> IP restriction and an expiry date, and delete this one (*Site administration → Server → Web services → Manage tokens*).

Shell variables used below:

```bash
TOKEN=$TOKEN
URL=http://localhost:9999/webservice/rest/server.php
```

Every call is an HTTP `POST` to `$URL` with `wstoken`, `wsfunction` and `moodlewsrestformat=json`. Errors also come
back as HTTP 200, with a JSON body containing `exception` / `errorcode` / `message`.

## Data found on this instance

| Entity | Value |
|---|---|
| Course | id **2**, "python" (shortname `test`) |
| Assignment | id **1**, cmid **3**, "test", max grade **100**, file + online-comment submission, feedback comments enabled, no marking workflow, `submissiondrafts = 0` |
| Teacher attachment | `BASICS-PART-01 (1).ipynb` (49,427 bytes), attached to the **assignment description**, not a student submission |
| Submissions | id 1 → admin (userid 2), id 2 → **sai** (userid 3). Both `status: new`, **no files uploaded** yet. |
| Grades before test | none |

---

## 1. Check the token: `core_webservice_get_site_info`

```bash
curl -s "$URL" -d wstoken=$TOKEN -d wsfunction=core_webservice_get_site_info -d moodlewsrestformat=json
```

Response (trimmed):

```json
{
  "sitename": "New Site",
  "username": "admin",
  "userid": 2,
  "siteurl": "http://localhost:9999",
  "functions": [
    {"name": "core_webservice_get_site_info", "version": "2025041401"},
    {"name": "mod_assign_get_assignments",    "version": "2025041400"},
    {"name": "mod_assign_get_submissions",    "version": "2025041400"},
    ...
  ]
}
```

✅ The token works and belongs to admin (userid 2). `functions` lists what the token may call.

## 2. Find assignments: `mod_assign_get_assignments`

```bash
curl -s "$URL" -d wstoken=$TOKEN -d wsfunction=mod_assign_get_assignments -d moodlewsrestformat=json \
  -d 'courseids[0]=2'
```

Response (trimmed):

```json
{
  "courses": [{
    "id": 2, "fullname": "python", "shortname": "test",
    "assignments": [{
      "id": 1, "cmid": 3, "course": 2, "name": "test",
      "submissiondrafts": 0, "grade": 100, "markingworkflow": 0,
      "teamsubmission": 0, "blindmarking": 0, "maxattempts": 1, "attemptreopenmethod": "untilpass",
      "duedate": 1790982000,
      "configs": [
        {"plugin": "file",     "subtype": "assignsubmission", "name": "enabled",            "value": "1"},
        {"plugin": "file",     "subtype": "assignsubmission", "name": "maxfilesubmissions", "value": "20"},
        {"plugin": "file",     "subtype": "assignsubmission", "name": "filetypeslist",      "value": ""},
        {"plugin": "comments", "subtype": "assignfeedback",   "name": "enabled",            "value": "1"}
      ],
      "introattachments": [{
        "filename": "BASICS-PART-01 (1).ipynb", "filesize": 49427,
        "fileurl": "http://localhost:9999/webservice/pluginfile.php/18/mod_assign/introattachment/0/BASICS-PART-01%20%281%29.ipynb",
        "mimetype": "application/json", "icon": "f/json"
      }]
    }]
  }],
  "warnings": []
}
```

✅ Assignment **id = 1** (this is the `assignmentid` for every later call), **cmid = 3**, max grade 100.
Note that Moodle stores `.ipynb` as **`application/json`**.

## 3. List submissions: `mod_assign_get_submissions`

```bash
curl -s "$URL" -d wstoken=$TOKEN -d wsfunction=mod_assign_get_submissions -d moodlewsrestformat=json \
  -d 'assignmentids[0]=1'
# optional filters: -d status=submitted   -d since=<unix time>   -d before=<unix time>
```

Response:

```json
{
  "assignments": [{
    "assignmentid": 1,
    "submissions": [
      {"id": 1, "userid": 2, "attemptnumber": 0, "status": "new", "groupid": 0,
       "timecreated": 1790419138, "timemodified": 1790419138,
       "plugins": [
         {"type": "file", "name": "File submissions",
          "fileareas": [{"area": "submission_files", "files": []}]},
         {"type": "comments", "name": "Submission comments"}],
       "gradingstatus": "notgraded"},
      {"id": 2, "userid": 3, "attemptnumber": 0, "status": "new", "groupid": 0,
       "timecreated": 1790419195, "timemodified": 1790419195,
       "plugins": [
         {"type": "file", "name": "File submissions",
          "fileareas": [{"area": "submission_files", "files": []}]},
         {"type": "comments", "name": "Submission comments"}],
       "gradingstatus": "notgraded"}
    ]
  }],
  "warnings": []
}
```

✅ Works. sai's submission is **id 2**, attempt 0, status `new`, and `files: []`. Nothing has been submitted yet.
Once a student uploads a notebook, each entry in `files[]` will carry a `fileurl` to download (step 5).

## 4. One student's status: `mod_assign_get_submission_status` (before grading)

```bash
curl -s "$URL" -d wstoken=$TOKEN -d wsfunction=mod_assign_get_submission_status -d moodlewsrestformat=json \
  -d assignid=1 -d userid=3
```

Response (trimmed):

```json
{
  "gradingsummary": {"participantcount": 1, "submissionssubmittedcount": 0, "submissionsneedgradingcount": 0},
  "lastattempt": {
    "submission": {"id": 2, "userid": 3, "attemptnumber": 0, "status": "new", "assignment": 1, "latest": 1,
                   "plugins": [{"type": "file", "fileareas": [{"area": "submission_files", "files": []}]}]},
    "locked": false, "graded": false, "canedit": true, "cansubmit": false,
    "gradingstatus": "notgraded"
  },
  "warnings": []
}
```

✅ Before grading: `graded: false`, `gradingstatus: notgraded`.

## 5. Download a notebook: `webservice/pluginfile.php`

This is a plain `GET` of the `fileurl` from the responses above, with `?token=` appended. It is not a `wsfunction`.

```bash
curl -s -o notebook.ipynb \
  "http://localhost:9999/webservice/pluginfile.php/18/mod_assign/introattachment/0/BASICS-PART-01%20%281%29.ipynb?token=$TOKEN"
```

Result: **HTTP 200, 49,427 bytes, `Content-Type: application/json`.** The file parses as a valid notebook:
`nbformat 4`, 98 cells, 35 of them code cells.

✅ Moodle returns the raw `.ipynb` byte-for-byte, ready to send to the evaluator. A student submission URL follows the
same pattern: `.../pluginfile.php/<contextid>/assignsubmission_file/submission_files/<submissionid>/<filename>?token=...`

## 6. Write the grade 9.5 + feedback: `mod_assign_save_grade`

```bash
curl -s "$URL" -d wstoken=$TOKEN -d wsfunction=mod_assign_save_grade -d moodlewsrestformat=json \
  -d assignmentid=1 -d userid=3 -d grade=9.5 -d attemptnumber=-1 \
  -d addattempt=0 -d workflowstate= -d applytoall=0 \
  --data-urlencode 'plugindata[assignfeedbackcomments_editor][text]=<p>Test grade 9.5 written via mod_assign_save_grade (curl).</p>' \
  -d 'plugindata[assignfeedbackcomments_editor][format]=1'
```

Response:

```json
null
```

✅ `null` means success; failures return an `exception` object. Parameter notes:

| Param | Value used | Meaning |
|---|---|---|
| `assignmentid` | 1 | assignment id (not cmid) |
| `userid` | 3 | student sai |
| `grade` | 9.5 | on the assignment's own scale (max 100 here). Ignored if a rubric/advanced grading is used. |
| `attemptnumber` | -1 | latest attempt. The real integration should send the exact attempt number it evaluated (here 0). |
| `addattempt` | 0 | don't reopen for another attempt |
| `workflowstate` | empty | no marking workflow on this assignment. With workflow on, use `readyforreview` / `released`. |
| `applytoall` | 0 | group assignments only |
| `plugindata[assignfeedbackcomments_editor][text/format]` | HTML, format 1 (= HTML) | the feedback comment the student sees |

## 7. Read the grade back: `mod_assign_get_grades`

```bash
curl -s "$URL" -d wstoken=$TOKEN -d wsfunction=mod_assign_get_grades -d moodlewsrestformat=json \
  -d 'assignmentids[0]=1'
```

Before step 6:

```json
{"assignments": [], "warnings": [{"item": "assignment", "itemid": 1, "warningcode": "3", "message": "No grades found"}]}
```

After step 6:

```json
{
  "assignments": [{
    "assignmentid": 1,
    "grades": [{"id": 1, "userid": 3, "attemptnumber": 0, "timecreated": 1790420100,
                "timemodified": 1790420100, "grader": 2, "grade": "9.50000"}]
  }],
  "warnings": []
}
```

✅ The grade is stored as **9.50000**, attempt 0. `grader: 2` is the token user (admin). With a dedicated LLM user,
this field would show that user, which gives you the audit trail.

## 8. Check what the student sees: `mod_assign_get_submission_status` (after grading)

```bash
curl -s "$URL" -d wstoken=$TOKEN -d wsfunction=mod_assign_get_submission_status -d moodlewsrestformat=json \
  -d assignid=1 -d userid=3
```

Response, `feedback` section:

```json
"feedback": {
  "grade": {"id": 1, "assignment": 1, "userid": 3, "attemptnumber": 0, "grader": 2, "grade": "9.50000"},
  "gradefordisplay": "9.50&nbsp;/&nbsp;100.00",
  "gradeddate": 1790420100,
  "plugins": [{
    "type": "comments", "name": "Feedback comments",
    "editorfields": [{"name": "comments", "description": "Feedback comments",
                      "text": "<p>Test grade 9.5 written via mod_assign_save_grade (curl).</p>", "format": 1}]
  }]
}
```

and `lastattempt.graded: true`, `lastattempt.gradingstatus: "graded"`.

✅ The student-facing view shows **9.50 / 100.00** plus the feedback comment.

## 9. Database check (gradebook sync)

```sql
SELECT userid, grade, grader, attemptnumber FROM mdl_assign_grades;
-- 3 | 9.50000 | 2 | 0
SELECT gg.userid, gg.finalgrade, gi.itemname FROM mdl_grade_grades gg
  JOIN mdl_grade_items gi ON gi.id = gg.itemid WHERE gi.itemmodule = 'assign';
-- 3 | 9.50000 | test
```

✅ The grade landed in **both** the assignment table (`assign_grades`) and the **course gradebook**
(`grade_grades`). `mod_assign_save_grade` keeps them in sync, unlike writing to the gradebook directly.

---

## Summary

| # | Call | Result |
|---|---|---|
| 1 | `core_webservice_get_site_info` | ✅ token valid (admin, userid 2) |
| 2 | `mod_assign_get_assignments` | ✅ assignment id 1 / cmid 3 / max 100 |
| 3 | `mod_assign_get_submissions` | ✅ submissions listed. **No student files yet** (status `new`). |
| 4 | `mod_assign_get_submission_status` | ✅ per-student status, `notgraded` before |
| 5 | `webservice/pluginfile.php?token=` | ✅ `.ipynb` downloaded intact (HTTP 200, 49,427 B, valid nbformat 4) |
| 6 | `mod_assign_save_grade` (9.5 + comment) | ✅ returned `null` (success) |
| 7 | `mod_assign_get_grades` | ✅ grade 9.50000 stored |
| 8 | `mod_assign_get_submission_status` | ✅ student sees "9.50 / 100.00" + feedback |
| 9 | DB check | ✅ assignment grade and gradebook both updated |

**Conclusion:** the whole read → download → grade → feedback path works over standard Moodle Web Services with curl.
That covers everything the FastAPI evaluator would need.

**Not yet tested:** a real student `.ipynb` **submission**. No student has uploaded a file yet. To finish the test, log in
as sai, upload a notebook to assignment "test" and submit, then re-run step 3 (the file and its `fileurl` will appear
under `submission_files`) and step 5 with that URL.

**Cleanup when done:** delete the test token and service, or set `enablewebservices` back to 0. The 9.5 grade for sai
is real data in the gradebook; change or remove it from the grading page if it shouldn't stay.
