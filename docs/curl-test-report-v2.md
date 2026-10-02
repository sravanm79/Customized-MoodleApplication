# Moodle Web Services — curl test report (v2)

**Date:** 2026-09-28 · **Moodle:** 5.0.1 (Build 20250609, `http://localhost:9999`) · **Protocol:** REST / JSON

End-to-end test of the full read → download → grade → verify path using plain `curl`.
Every call is an HTTP `POST` to the REST endpoint (except Step 5 which is a `GET` file download).

---

## Web Service functions used

| Function | Purpose |
|---|---|
| `core_webservice_get_site_info` | Verify the token is valid and list which functions it can call |
| `mod_assign_get_assignments` | List assignments in a course — returns `id`, `cmid`, max grade, due dates, attached files |
| `mod_assign_get_submissions` | List all student submissions for an assignment — status, files with download URLs, grading status |
| `mod_assign_get_submission_status` | Detailed view for one student — includes the `feedback` block after grading |
| `mod_assign_get_grades` | Read numeric grades stored against an assignment |
| `mod_assign_save_grade` | Write a grade + feedback comment for one student into the assignment table and the gradebook |
| `mod_assign_list_participants` | List enrolled students with their submission/grading state (registered but not called in this test) |
| `core_files_get_files` | Browse a file area directly by contextid/component/filearea (registered but not called in this test) |

`webservice/pluginfile.php?token=` is **not** a WS function — it is a plain HTTP GET file-download endpoint that uses the same token for authentication.

---

## Setup

Shell variables used in every command:

```bash
TOKEN=$TOKEN
URL=http://localhost:9999/webservice/rest/server.php
```

Every POST call uses three mandatory fields:

| Field | Value | Purpose |
|---|---|---|
| `wstoken` | `$TOKEN` | Identifies and authenticates the caller |
| `wsfunction` | the function name | Tells Moodle which API to invoke |
| `moodlewsrestformat` | `json` | Returns JSON instead of XML |

Errors return HTTP 200 with a JSON body containing `"exception"` / `"errorcode"` / `"message"` — always check the body, not just the HTTP status.

---

## Data on this instance

| Entity | Value |
|---|---|
| Course | id **2**, "python" (shortname `test`) |
| Assignment | id **2**, cmid **3**, "basics", max grade **100** |
| Question notebook | `Python_Basics_Assignment.ipynb` (27,244 bytes), attached to assignment description |
| Admin | userid **2** |
| Student sai | userid **3**, submission id **4**, `status: submitted`, attempt **0** |
| Student admin | userid **2**, submission id **3**, `status: new`, no files uploaded |

---

## Step 1 — Verify the token: `core_webservice_get_site_info`

### Why
Confirm the token is accepted, identify which Moodle user it belongs to, and see the exact list of functions the service allows.

### curl command

```bash
curl -s "$URL" \
  -d "wstoken=$TOKEN" \
  -d "wsfunction=core_webservice_get_site_info" \
  -d "moodlewsrestformat=json" | python3 -m json.tool
```

### Response (trimmed)

```json
{
    "sitename": "New Site",
    "username": "admin",
    "firstname": "Admin",
    "lastname": "User",
    "fullname": "Admin User",
    "userid": 2,
    "siteurl": "http://localhost:9999",
    "functions": [
        {"name": "core_webservice_get_site_info",      "version": "2025041401"},
        {"name": "mod_assign_get_assignments",          "version": "2025041400"},
        {"name": "mod_assign_get_submissions",          "version": "2025041400"},
        {"name": "mod_assign_get_submission_status",    "version": "2025041400"},
        {"name": "mod_assign_get_grades",               "version": "2025041400"},
        {"name": "mod_assign_save_grade",               "version": "2025041400"},
        {"name": "mod_assign_list_participants",        "version": "2025041400"},
        {"name": "core_files_get_files",                "version": "2025041401"}
    ],
    "downloadfiles": 1,
    "uploadfiles": 0,
    "release": "5.0.1 (Build: 20250609)",
    "version": "2025041401",
    "userissiteadmin": true
}
```

### Result
✅ Token valid. Belongs to admin (userid 2). All 8 functions registered. `downloadfiles: 1` confirms file download is enabled.

---

## Step 2 — Find assignments: `mod_assign_get_assignments`

### Why
Get the `assignmentid` (different from `cmid`) that all submission and grading calls require. Also confirms max grade, due date, and any teacher-attached files.

### curl command

```bash
curl -s "$URL" \
  -d "wstoken=$TOKEN" \
  -d "wsfunction=mod_assign_get_assignments" \
  -d "moodlewsrestformat=json" \
  -d "courseids[0]=2" | python3 -m json.tool
```

### Response (trimmed)

```json
{
    "courses": [{
        "id": 2, "fullname": "python", "shortname": "test",
        "assignments": [{
            "id": 2, "cmid": 3, "course": 2, "name": "basics",
            "submissiondrafts": 0, "grade": 100, "markingworkflow": 0,
            "duedate": 1791154800,
            "configs": [
                {"plugin": "file",     "subtype": "assignsubmission", "name": "enabled",   "value": "1"},
                {"plugin": "comments", "subtype": "assignfeedback",   "name": "enabled",   "value": "1"}
            ],
            "introattachments": [{
                "filename": "Python_Basics_Assignment.ipynb",
                "filesize": 27244,
                "fileurl": "http://localhost:9999/webservice/pluginfile.php/21/mod_assign/introattachment/0/Python_Basics_Assignment.ipynb",
                "mimetype": "application/json"
            }]
        }]
    }],
    "warnings": []
}
```

### Result
✅ **`assignmentid = 2`** — used in every subsequent call. `cmid = 3`. Max grade 100. Feedback comments enabled. `markingworkflow: 0` (no workflow). Question notebook attached to the description.

---

## Step 3 — List submissions: `mod_assign_get_submissions`

### Why
Find which students have submitted, get their submission ids, and — critically — get the `fileurl` for each uploaded notebook so the evaluator can download it.

### curl command

```bash
curl -s "$URL" \
  -d "wstoken=$TOKEN" \
  -d "wsfunction=mod_assign_get_submissions" \
  -d "moodlewsrestformat=json" \
  -d "assignmentids[0]=2" | python3 -m json.tool
```

### Response

```json
{
    "assignments": [{
        "assignmentid": 2,
        "submissions": [
            {
                "id": 3, "userid": 2, "attemptnumber": 0,
                "status": "new", "gradingstatus": "notgraded",
                "plugins": [{"type": "file", "fileareas": [{"area": "submission_files", "files": []}]}]
            },
            {
                "id": 4, "userid": 3, "attemptnumber": 0,
                "status": "submitted", "gradingstatus": "notgraded",
                "plugins": [{
                    "type": "file",
                    "fileareas": [{"area": "submission_files", "files": [{
                        "filename": "Python_Basics_Assignment_ANSWER_KEY.ipynb",
                        "filesize": 42552,
                        "fileurl": "http://localhost:9999/webservice/pluginfile.php/21/assignsubmission_file/submission_files/4/Python_Basics_Assignment_ANSWER_KEY.ipynb",
                        "mimetype": "application/json"
                    }]}]
                }]
            }
        ]
    }],
    "warnings": []
}
```

### Result
✅ Two submissions found. Sai (userid 3) has a real submitted notebook — **submission id 4**, attempt 0. The `fileurl` is ready for download in Step 5. Admin (userid 2) has not uploaded anything (`status: new`, `files: []`).

---

## Step 4 — One student's status before grading: `mod_assign_get_submission_status`

### Why
Get a complete per-student snapshot before writing anything — confirms `graded: false` and `gradingstatus: notgraded` as the baseline. The same call after grading (Step 8) shows the difference.

### curl command

```bash
curl -s "$URL" \
  -d "wstoken=$TOKEN" \
  -d "wsfunction=mod_assign_get_submission_status" \
  -d "moodlewsrestformat=json" \
  -d "assignid=2" \
  -d "userid=3" | python3 -m json.tool
```

### Response (trimmed)

```json
{
    "gradingsummary": {
        "participantcount": 1,
        "submissionssubmittedcount": 1,
        "submissionsneedgradingcount": 1
    },
    "lastattempt": {
        "submission": {
            "id": 4, "userid": 3, "attemptnumber": 0,
            "status": "submitted", "latest": 1,
            "plugins": [{"type": "file", "fileareas": [{"area": "submission_files", "files": [{
                "filename": "Python_Basics_Assignment_ANSWER_KEY.ipynb",
                "filesize": 42552,
                "fileurl": "http://localhost:9999/webservice/pluginfile.php/21/assignsubmission_file/submission_files/4/Python_Basics_Assignment_ANSWER_KEY.ipynb"
            }]}]}]
        },
        "locked": false,
        "graded": false,
        "gradingstatus": "notgraded"
    },
    "warnings": []
}
```

### Result
✅ Baseline confirmed: `graded: false`, `gradingstatus: notgraded`, `submissionsneedgradingcount: 1`. No `feedback` block yet.

---

## Step 5 — Download the notebook: `pluginfile.php` (GET)

### Why
This is how the FastAPI evaluator fetches the `.ipynb` — a plain GET of the `fileurl` from Step 3 with `?token=` appended. Not a WS function call; it uses the same token for auth via the "Can download files" service setting.

### curl command

```bash
curl -s -o /tmp/sai_submission.ipynb \
  "http://localhost:9999/webservice/pluginfile.php/21/assignsubmission_file/submission_files/4/Python_Basics_Assignment_ANSWER_KEY.ipynb?token=$TOKEN" \
  -w "HTTP %{http_code}, size %{size_download} bytes\n"
```

### Response

```
HTTP 200, size 42552 bytes
```

### Result
✅ Notebook downloaded byte-for-byte (42,552 bytes). Moodle serves `.ipynb` as `application/json`. The file is valid JSON ready to be sent to the evaluator. The URL pattern for student submissions is always:
`.../pluginfile.php/<contextid>/assignsubmission_file/submission_files/<submissionid>/<filename>?token=...`

---

## Step 6 — Write grade + feedback: `mod_assign_save_grade`

### Why
This is the write-back step — what the FastAPI evaluator calls after scoring the notebook. It writes to both the assignment grades table (`mdl_assign_grades`) and the course gradebook (`mdl_grade_grades`) in one call.

### curl command

```bash
curl -s "$URL" \
  -d "wstoken=$TOKEN" \
  -d "wsfunction=mod_assign_save_grade" \
  -d "moodlewsrestformat=json" \
  -d "assignmentid=2" \
  -d "userid=3" \
  -d "grade=85" \
  -d "attemptnumber=0" \
  -d "addattempt=0" \
  -d "workflowstate=" \
  -d "applytoall=0" \
  --data-urlencode "plugindata[assignfeedbackcomments_editor][text]=<p>Good work on Python basics. Score: 85/100.</p>" \
  -d "plugindata[assignfeedbackcomments_editor][format]=1"
```

### Parameter notes

| Param | Value | Meaning |
|---|---|---|
| `assignmentid` | 2 | Assignment id (not cmid) |
| `userid` | 3 | Student sai |
| `grade` | 85 | Points on the assignment's own scale (max 100). Ignored if a rubric is used. |
| `attemptnumber` | 0 | The exact attempt number evaluated. Use `-1` for "latest" only in interactive tooling; in automated code always send the real attempt number. |
| `addattempt` | 0 | Do not reopen for another attempt |
| `workflowstate` | empty | No marking workflow on this assignment. With workflow on, use `readyforreview` so a teacher reviews before the student sees the grade. |
| `applytoall` | 0 | Group assignments only |
| `plugindata[assignfeedbackcomments_editor][text/format]` | HTML, format 1 | Feedback comment the student sees |

### Response

```
null
```

### Result
✅ `null` = success. Any failure returns a JSON `{"exception": ..., "errorcode": ..., "message": ...}` object instead.

---

## Step 7 — Read the grade back: `mod_assign_get_grades`

### Why
Verify the grade landed in the assignment grades table. The evaluator can use this to confirm its write was accepted.

### curl command

```bash
curl -s "$URL" \
  -d "wstoken=$TOKEN" \
  -d "wsfunction=mod_assign_get_grades" \
  -d "moodlewsrestformat=json" \
  -d "assignmentids[0]=2" | python3 -m json.tool
```

### Response

```json
{
    "assignments": [{
        "assignmentid": 2,
        "grades": [{
            "id": 1,
            "userid": 3,
            "attemptnumber": 0,
            "timecreated": 1790588621,
            "timemodified": 1790588621,
            "grader": 2,
            "grade": "85.00000"
        }]
    }],
    "warnings": []
}
```

### Result
✅ `grade: "85.00000"` stored for userid 3, attempt 0. `grader: 2` is the token user (admin). In production with a dedicated "LLM Grader" user, this field gives the audit trail showing which grades were written by the LLM vs a human teacher.

---

## Step 8 — Check what the student sees: `mod_assign_get_submission_status` (after grading)

### Why
Confirm the full student-facing view: grade display string, feedback comment, and that `graded` / `gradingstatus` flipped from the Step 4 baseline.

### curl command

```bash
curl -s "$URL" \
  -d "wstoken=$TOKEN" \
  -d "wsfunction=mod_assign_get_submission_status" \
  -d "moodlewsrestformat=json" \
  -d "assignid=2" \
  -d "userid=3" | python3 -m json.tool
```

### Response (trimmed to changed/new fields)

```json
{
    "gradingsummary": {
        "submissionsneedgradingcount": 0
    },
    "lastattempt": {
        "graded": true,
        "gradingstatus": "graded"
    },
    "feedback": {
        "grade": {
            "id": 1, "assignment": 2, "userid": 3,
            "attemptnumber": 0, "grader": 2, "grade": "85.00000"
        },
        "gradefordisplay": "85.00 / 100.00",
        "gradeddate": 1790588621,
        "plugins": [{
            "type": "comments",
            "name": "Feedback comments",
            "editorfields": [{
                "name": "comments",
                "description": "Feedback comments",
                "text": "<p>Good work on Python basics. Score: 85/100.</p>",
                "format": 1
            }]
        }]
    },
    "warnings": []
}
```

### Result
✅ Student-facing view shows **85.00 / 100.00** plus the feedback comment. `graded: true`, `gradingstatus: graded`, `submissionsneedgradingcount: 0`.

---

## Before vs After comparison (Steps 4 → 8)

| Field | Step 4 (before) | Step 8 (after) |
|---|---|---|
| `graded` | `false` | **`true`** |
| `gradingstatus` | `notgraded` | **`graded`** |
| `submissionsneedgradingcount` | `1` | **`0`** |
| `feedback` block | absent | **grade + comment present** |
| `gradefordisplay` | — | **`85.00 / 100.00`** |

---

## Final summary

| # | Call | Result |
|---|---|---|
| 1 | `core_webservice_get_site_info` | ✅ Token valid (admin, userid 2), 8 functions available |
| 2 | `mod_assign_get_assignments` | ✅ Assignment id 2 / cmid 3 / max 100 / question notebook present |
| 3 | `mod_assign_get_submissions` | ✅ Sai (userid 3) has submitted `Python_Basics_Assignment_ANSWER_KEY.ipynb` (42,552 bytes) |
| 4 | `mod_assign_get_submission_status` | ✅ Baseline: `graded: false`, `gradingstatus: notgraded` |
| 5 | `pluginfile.php?token=` (GET) | ✅ Notebook downloaded, HTTP 200, 42,552 bytes |
| 6 | `mod_assign_save_grade` (85 + comment) | ✅ Returned `null` (success) |
| 7 | `mod_assign_get_grades` | ✅ `grade: "85.00000"` confirmed in assignment table |
| 8 | `mod_assign_get_submission_status` | ✅ Student sees `85.00 / 100.00` + feedback comment |

**Conclusion:** the complete read → download → grade → verify path works over standard Moodle Web Services with plain curl. This covers everything the FastAPI LLM evaluator needs to integrate with Moodle.

---

## Notes for production

- **Do not use the admin token in production.** Create a dedicated "LLM Grader" user with a Teacher role scoped to the relevant courses, give it its own token with an IP restriction and an expiry date.
- **Use `workflowstate=readyforreview`** when the assignment has marking workflow enabled — this hides the LLM grade from the student until a teacher reviews and releases it.
- **Use `attemptnumber=0`** (the real attempt number), not `-1`, in automated code. `-1` means "latest" but makes staleness checks harder.
- **The `grader` field** in `mod_assign_get_grades` is your audit trail — it shows which user (human or LLM service account) wrote each grade.
- **Token persistence:** web services and the token must be re-enabled after a container restart. In production, enable web services permanently via the Moodle admin UI or a provisioning script.
