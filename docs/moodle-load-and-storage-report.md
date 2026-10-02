# Moodle — Load test (1000 students, CPU only) & notebook storage sizing

**Date:** 2026-09-29 · **Moodle:** 5.0.1 (Build 20250609), Bitnami Docker image, `http://localhost:9999` · **Tool:** k6 v0.54.0
**Scripts & raw results:** `../loadtest/` (see *Reproduce* at the end)

---

## 1. Notes (setup reference)

| Item | Value |
|---|---|
| Moodle download | https://download.moodle.org/ |
| Installation guide | https://docs.moodle.org/502/en/Installing_Moodle |
| Docker compose reference | https://github.com/bitnami/containers/blob/main/bitnami/moodle/docker-compose.yml |
| Admin login | `admin` / `AdminPassword123!` |
| Load-test students | `loadtest0001` … `loadtest1000`, password `LoadTest#2026`, enrolled in course id 2 (`test`) |

### Server specs

> The spec sheet in the original notes (i7-13700, 24 threads, 31 GB) does **not** match the SSH server the tests ran on.
> The table below is what `lscpu`, `free` and `df` report on the actual server.

| | Notes (original) | **Actual test server — `vocab-server3`** |
|---|---|---|
| OS | Ubuntu 24.04.3 LTS 64-bit | Ubuntu, kernel 6.8.0-138 |
| CPU | Intel Core i7-13700 | **Intel Core i7-14700** (8 P-cores + 12 E-cores) |
| CPU capacity | 16 cores / 24 threads | **20 cores / 28 threads**, max 5.3 GHz |
| RAM | 31 GB | **62 GB** (~32 GB available; other services run here) |
| Primary disk | ~477 GB NVMe, ~247 GB free | 456 GB NVMe, **133 GB free** ← Moodle Docker volumes live here (`/var/lib/docker`) |
| Additional disk | ~931 GB | 1.8 TB, 285 GB free (not used by Moodle) |
| Swap | 2 GB | 2 GB |
| GPU | — | RTX 4000 Ada — **not used**: Moodle containers run with `runc`, no GPU devices attached (verified) |

The server is shared with other containers (omni-tts, ASR, diarization, open-webui…), so every number below was measured **with those services running**.

### Moodle hardware requirements (official) vs. what we measured

| Component | Minimum | Recommended / practical | **Measured here, 1000 active students** |
|---|---|---|---|
| Disk space | 200 MB for Moodle code | 5 GB+, depending on course content and uploads | Code 398 MB · DB ~300 MB · ~55 KB per notebook submission (§3) |
| Processor | 1 GHz | 2 GHz dual-core or higher | **~4.5 cores peak** (≈16 % of this CPU) |
| Memory (RAM) | 512 MB | 1 GB+; 8 GB+ for larger production | **~1.7 GB peak** (Apache+PHP 1.27 GB, MariaDB 0.43 GB) |
| Web & DB servers | Same server OK | Separate front-end and DB for larger deployments | Same server was fine at 1000 users (DB peak < 1 core) |
| Additional storage | Depends on content | Course files, videos, backups, logs, uploads | See projections in §3 |

---

## 2. Experiment 1 — How many students can use it at once? (target: 1000, CPU only)

### What a "student" does in the test
Each k6 virtual user logs in as its **own real account** and repeats, like a person clicking around:
dashboard → course page → assignment page → grades, with **5–15 s think time** between pages.
Every page also makes the navbar AJAX calls a browser makes (messages + notifications).
Every response is checked for status 200, a Moodle error page, and being redirected back to login.
The load generator ran pinned to 4 CPU cores (`taskset -c 24-27`) so it didn't take CPU from Moodle.

### Results

| Test | Students | Page p50 | Page p95 | Errors | Host CPU peak | Verdict |
|---|---|---|---|---|---|---|
| A · stock config | 50 → 300 | 28 ms | 164 ms | 0 / 9,556 | ~4 % | ✅ |
| B · stock config | 250 → **1000** | **4.7 s** | **9.9 s** | 0 / 79,112 | ~8 % | ❌ slow from ~750 students |
| C · **tuned config** | 250 → **1000** | **31 ms** | **144 ms** | 0 / 113,512 | 16.5 % | ✅ |
| D · **login storm + submission rush** (tuned) | 1000 log in within 60 s, all submit a notebook | 30 ms | 52 ms | 0 / 11,000 | 15 % | ✅ |

Test D detail: login p95 **93 ms**, notebook upload p95 **14 ms**, "Save submission" p95 **75 ms**, **1000 / 1000 submissions stored**.

Test C per stage (pages only):

| Students | Pages/s | p50 | p95 | Host CPU | Moodle (Apache+PHP) CPU | MariaDB CPU | Moodle RAM |
|---|---|---|---|---|---|---|---|
| 250 | 25 | 28 ms | 58–90 ms | 4 % | 0.9 cores | 0.2 cores | 0.46 GB |
| 500 | 50 | 28 ms | 124–172 ms | 7.5 % | 1.7 cores | 0.35 cores | 0.72 GB |
| 750 | 75 | 30 ms | 150–188 ms | 11 % | 2.7 cores | 0.55 cores | 0.98 GB |
| 1000 | 100 | 32 ms | 99–175 ms | 16.5 % | 3.6 cores | 0.9 cores | 1.27 GB |

### Why the stock config failed (Test B) — and the fix
CPU was **not** the problem (host sat at ~7 % CPU while pages took 10 s). Throughput was stuck at ~100 req/s.

The Bitnami image runs PHP **inside Apache** (`mod_php`, *prefork* MPM). In prefork, **one Apache process = one client connection**, and it stays reserved for `KeepAliveTimeout` (5 s) after every request.
With `MaxRequestWorkers 250`, 250 idle keep-alive connections occupy every worker, so every new page load waits in the queue until one times out.
That's why pages took 5–10 s while AJAX calls on an already-open connection took 5 ms.

> Note: `/opt/bitnami/php/etc/php-fpm.d/www.conf` has `pm.max_children = 5`, but PHP-FPM **is not running** in this image — that setting has no effect.

**Changes applied (live, in the running containers):**

| Setting | File | Before | After |
|---|---|---|---|
| `ServerLimit` / `MaxRequestWorkers` | `/opt/bitnami/apache/conf/extra/httpd-mpm.conf` (prefork block) | 250 | **1000** |
| `StartServers` / `MinSpareServers` / `MaxSpareServers` | same | 5 / 5 / 10 | 50 / 50 / 150 |
| `MaxConnectionsPerChild` | same | 0 | 10000 |
| `KeepAliveTimeout` | `/opt/bitnami/apache/conf/extra/httpd-default.conf` | 5 | **2** |
| `max_connections` | MariaDB (`SET GLOBAL`) | 151 | 1100 |
| `innodb_buffer_pool_size` | MariaDB (`SET GLOBAL`) | 128 MB | 2 GB |

Original and tuned config files are in `../loadtest/tuning/`.

⚠️ **These changes are not permanent yet.**
- The Apache files survive `docker restart`, but **not** `docker compose down/up`, because the container is recreated.
- The MariaDB values are lost when `moodle_db` restarts.

To persist them, mount the files from `docker-compose.yaml`:

```yaml
  mariadb:
    volumes:
      - mariadb_data:/bitnami/mariadb
      - ./loadtest/tuning/mariadb-tuning.cnf:/opt/bitnami/mariadb/conf/my_custom.cnf:ro
  moodle:
    volumes:
      - moodle_data:/bitnami/moodle
      - moodledata_data:/bitnami/moodledata
      - ./loadtest/tuning/httpd-mpm.conf:/opt/bitnami/apache/conf/extra/httpd-mpm.conf:ro
      - ./loadtest/tuning/httpd-default.conf:/opt/bitnami/apache/conf/extra/httpd-default.conf:ro
```

### Answer
✅ **This server supports 1000 simultaneous active students on CPU alone**, with the tuned config: page p95 < 200 ms, zero errors, ~16 % CPU, ~1.7 GB RAM.
It also handles all 1000 logging in within one minute and all 1000 submitting at once (a deadline rush).

**Maximum capacity (beyond 1000): not measured yet.** Testing it needs more than the 1000 test accounts; creating more was not approved in this run.
At 1000 students the machine used ~4.5 cores for ~100 pages/s + ~100 AJAX calls/s, and the cost grew linearly.
A rough CPU-only extrapolation, keeping ~30 % headroom for the other services on this box, is **~4,000–5,000 active students**.

The first limit above 1000 will be `MaxRequestWorkers 1000` (one Apache process per connection), not the CPU. Beyond ~1000 users, switch Apache to the event MPM + PHP-FPM, which decouples idle connections from PHP workers.

### Limitations
- **Static files not fetched.** k6 requests the HTML pages and AJAX calls only, not CSS/JS/images. Moodle serves these with long cache headers, so a real browser downloads them mostly once per session. Expect somewhat more Apache load on first visits.
- **Single test course.** The test course is small (one assignment). Big courses (many activities, forums, quizzes) render slower. Quizzes weren't tested; a quiz with 1000 simultaneous attempts is heavier than browsing.
- **Same-host load generator.** The load generator ran on the same host (4 pinned cores), so network latency is ~0. Real users over the internet add their own latency and bandwidth: a 42 KB upload × 1000 is ~43 MB total.

---

## 3. Experiment 2 — Storage per `.ipynb` submission (1000 students)

**Reference submission:** `Python_Basics_Assignment_ANSWER_KEY.ipynb` = **42,552 bytes (41.6 KB)**
- 65 cells: 33 markdown + 32 code
- 32 of the code cells have outputs, and none embed images
- Content: 16 KB source + 4 KB outputs + JSON/metadata
- Compresses to 8 KB with gzip

**Method:**
1. All 1000 students uploaded and submitted through the real Moodle web UI in Test D.
2. Each copy was made **unique**, with the student name + a random value in the notebook metadata, so each file is ~42.9 KB.
   This matters because Moodle stores files by SHA-1 content hash. 1000 byte-identical uploads would be stored **once**, which would under-count real storage.
3. Storage was snapshotted before and after (`storage_snapshot.sh`).

### Measured cost of one submission

| Where | What | Bytes per submission |
|---|---|---|
| `moodledata/filedir` | The notebook file itself (stored once, by content hash) | 42,897 content · **~49 KB on disk** (4 KB blocks + hash directories) |
| `moodledata/filedir` | The draft copy made during upload | **0 extra** — it shares the same stored file (verified: 0 unshared draft files) |
| DB `mdl_files` | 4 rows (draft + submission, each a file row + a folder row) × ~436 B | ~1.7 KB |
| DB `mdl_assign_submission`, `mdl_grade_grades` | submission + grade rows | ~0.8 KB |
| DB `mdl_logstore_standard_log` | ~14 event-log rows (login, views, upload, submit) × ~241 B | ~3.4 KB |
| **Total** | | **≈ 55 KB per student per notebook** |

Measured totals for 1000 submissions:
- `filedir` grew from 0.2 MB to 48.2 MB: **+48 MB**, with 1000 new files
- Database file/grade/log tables: **+6 MB**

### 1000 students — projections

| Scenario | Per submission | 1 assignment | 10 assignments | 20 assignments |
|---|---|---|---|---|
| **This notebook (text outputs, measured)** | ~55 KB | **~55 MB** | ~0.55 GB | ~1.1 GB |
| Notebook with a few plots (~500 KB, estimate) | ~0.5 MB | ~0.5 GB | ~5 GB | ~10 GB |
| Heavy notebook (many plots / data, ~2 MB, estimate) | ~2 MB | ~2 GB | ~20 GB | ~40 GB |

**Other storage that grows with 1000 students:**
- **Event log.** About 1 row (~240 B) per page view. For example, 1000 students × 30 page views/day × 120 days ≈ 3.6 M rows ≈ **~0.9 GB per semester**.
  - The load tests alone added ~150,000 rows (36 MB).
  - Set *Site admin → Plugins → Logging → Standard log → Keep logs for* to cap it.
- **Resubmissions.** A replaced file goes to `trashdir` and is purged by cron. Drafts are cleaned by cron after ~4 days, so they're not permanent.
- **Sessions.** `moodledata/sessions` reached 12 MB for ~3,000 sessions. This is temporary; cron cleans it.
- **Course backups (`.mbz`, with user data).** These are zip-compressed. Notebooks compress ~5×, so a backup adds ~10–15 KB per submission, **for each backup kept**.
- **Grader downloads.** If a grader (e.g. LLM grading) downloads the submissions, that uses local disk on the grader side, not in Moodle.

**Recommendation:** for 1000 students with ~20 notebook assignments per term, plan **~5–10 GB** for `moodledata` + database, plus the same again for backups. Increase this if notebooks contain images or datasets.
The Docker volumes are on the root NVMe (**133 GB free**), which is enough. For long-term growth, consider moving `moodledata_data` to the 1.8 TB disk.

---

## 4. Reproduce

All in `../loadtest/`:

| File | Purpose |
|---|---|
| `create_users.php` | `docker cp` into `moodle_app`, then `docker exec -u daemon moodle_app php /tmp/create_users.php 1000 2` (add `--delete` to remove all `loadtest*` users) |
| `moodle_student.js` | k6 scenario. Env: `STAGES="250:1m,250:2m,…"`, `THINK_MIN`/`THINK_MAX`, `UPLOAD=1`, `SUBMIT_ONLY=1` |
| `monitor.sh` | Samples host CPU, container CPU/RAM, Apache process count every ~5 s into a CSV |
| `analyze.py` | Per-minute students / pages/s / p50 / p95 / CPU / RAM table: `python3 analyze.py results/C_tuned1000` |
| `storage_snapshot.sh` | moodledata / filedir / DB table sizes |
| `reference_submission.ipynb` | The notebook used for uploads |
| `tuning/` | Original (`*.orig`) and tuned Apache/MariaDB config |
| `results/` | k6 output, summaries (JSON), raw per-request CSVs, monitor CSVs, storage snapshots for tests A–D |

Example (Test C):
```bash
cd loadtest
./monitor.sh results/C_monitor.csv & MON=$!
STAGES="250:1m,250:2m,500:1m,500:2m,750:1m,750:2m,1000:1m,1000:4m" taskset -c 24-27 ./k6 run \
  --summary-export results/C_summary.json --out csv=results/C_raw.csv.gz moodle_student.js
kill $MON
```

**Test data still in Moodle:** 1000 `loadtest*` users, each with a submitted notebook in assignment "basics", plus ~150k log rows.
Remove with `create_users.php --delete` when no longer needed.
