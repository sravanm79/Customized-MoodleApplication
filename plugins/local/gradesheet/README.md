# local_gradesheet — Grade sheets

Teachers upload a CSV or Excel grade sheet for a course; students see their own normalised score, their percentile
and an anonymised class distribution. Moodle 5.0+.

## How it works

- **Upload** (`upload.php`, `local/gradesheet:manage`, editing teachers and managers): choose a sheet name, the
  maximum score and a `.csv`, `.xlsx`, `.xls` or `.ods` file. The student ID and score columns are guessed from the
  header row ("Roll No", "ID number", "Username", "Email" / "Marks", "Score", "Total", ...) and can be changed. The
  preview matches every row to the course's **active students** (ID number, then username, then email; case
  insensitive) and lists what will be skipped: unknown or ambiguous IDs, duplicates, missing or non-numeric scores,
  scores outside 0..maximum. Only the valid rows are imported.
- **Storage**: each sheet is a manual **gradebook item**; the scores are its grades (gradebook reports, backup,
  privacy, later corrections in the gradebook are reflected here). `local_gradesheet` only lists the sheets.
- **Teacher view** (`index.php`): per sheet, average, median, quartiles, lowest, highest, standard deviation
  (normalised to %), a box plot, a 10 % histogram and every student's score; open gradebook; delete (with the item).
- **Student view** (`index.php`, `local/gradesheet:viewstats`, students): their score, normalised grade and, once at
  least *Minimum class size for statistics* students have a score (default 5; Site administration > Plugins > Local
  plugins > Grade sheets), their percentile and the class average, median, quartiles and distribution with their own
  bucket highlighted. Never other students' scores, the lowest or highest score. Sheets whose gradebook item is hidden
  are not shown to students.
- **Navigation**: "Grade sheets" in the course navigation (More).

## Files

| File | Purpose |
|---|---|
| `upload.php`, `index.php` | Controllers: upload/preview/import; sheet list for teachers and students, delete |
| `classes/local/sheet_parser.php` | Reads CSV (BOM, `,` `;` tab, quoted fields, Windows-1252) and spreadsheets (PhpSpreadsheet) |
| `classes/local/sheet_validator.php` | Column guessing, student matching, score parsing (`85,5`, `80%`) and row checks |
| `classes/local/sheet_manager.php` | Gradebook item and grades: import, read, delete |
| `classes/local/stats.php` | Mean, median, quartiles (as QUARTILE.INC), percentile rank, histogram |
| `classes/output/sheet_view.php` | Teacher and student template context |
| `templates/` | Teacher and student pages, sheet cards, histogram, upload preview |
| `tests/` | PHPUnit tests for the parser, statistics and score parsing |

## Deploy (docker)

```bash
docker exec moodle_app mkdir -p /bitnami/moodle/local/gradesheet
tar -C plugins/local/gradesheet -cf - . | docker exec -i moodle_app tar -C /bitnami/moodle/local/gradesheet -xf -
docker exec moodle_app chown -R daemon:daemon /bitnami/moodle/local/gradesheet
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/upgrade.php --non-interactive
docker exec -u daemon moodle_app php /bitnami/moodle/admin/cli/purge_caches.php
```
