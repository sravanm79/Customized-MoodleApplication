# Moodle capacity test — results (30 Sep 2026)

**Question:** how many *concurrently active* students (logged in and clicking, not just enrolled) can this Moodle
support on this hardware? Answered separately for three workloads, each against the pass/fail SLA below.

## Answer

| Workload | Capacity (students active at once) | What runs out first |
|---|---|---|
| Normal use: pages, forum, multiple-choice quiz | **3,000** | Host CPU (Moodle PHP ≈ 22 cores at saturation) |
| Coding quiz: CodeRunner answers run in Jobe | **2,000** | Host CPU, shared by Moodle PHP + Jobe sandbox + DB |
| Jupyter notebooks (a personal server + kernel each) | **≈ 140** | Host RAM (≈ 180 MB per student) |

The three numbers are **not additive**: they share the same 28 CPU threads and 62 GB RAM.

## Hardware condition

| | |
|---|---|
| CPU | Intel i7-14700, 20 cores / 28 threads |
| RAM | 62 GB (≈ 32 GB available to the test; the rest is used by other services) |
| Disk | NVMe SSD (Docker volumes) |
| Host | **Shared** with ASR, TTS, diarization and Open WebUI containers. Idle during the test; under their own load, capacity is lower. Swap 2 GB, ~95 % used but no swap activity during any test. |
| Stack | Bitnami Moodle 5.0.1 in Docker, MariaDB 11 (`max_connections` 1100, 2 GB buffer pool), file sessions, no Redis/memcached, cron every minute. No per-container CPU/RAM limits. |
| Web tier (after tuning) | Apache **event MPM** + **PHP-FPM** (`pm.max_children` 112), `MaxRequestWorkers` 1024, `KeepAliveTimeout` 2 s |
| Load generator | Locust 2.46, 8 processes, **on the same host** (used ≈ 1 core at the highest load) |

## SLA (a stage passes only if all hold)

Page loads p95 < 2.5 s · graded submission p95 < 5 s · Jupyter server start p95 < 10 s and cell run p95 < 5 s ·
errors < 1 % · host CPU average < 90 % · no swapping. Each stage is held 3.5 min; the first 90 s (ramp-up) are
excluded. Capacity = highest stage where that stage and all lower ones pass.

## Track 1 — normal use

Each student: dashboard, course page, a reading page, a forum thread, and a multiple-choice quiz attempt, with
5–15 s between clicks (10–20 s on the quiz).

**Before tuning** (Apache prefork + mod_php, as shipped): capacity **750**. The limit was Apache's **256 worker
processes**, not hardware — host CPU was at 10 %. Browsers keep idle connections open, and in prefork each open
connection holds a whole worker. (A tuned `httpd-mpm.conf` raising this to 1000 existed but its `Include` line was
commented out.)

**After tuning** (event MPM + PHP-FPM, see `../moodle-tuning/`):

| Students | req/s | Page p95 | Quiz submit p95 | Errors | Host CPU | Moodle CPU (cores) | Verdict |
|---|---|---|---|---|---|---|---|
| 1,000 | 112 | 0.05 s | 0.06 s | 0 | 16 % | 3.4 | PASS |
| 2,000 | 224 | 0.07 s | 0.08 s | 0 | 43 % | 9.8 | PASS |
| **3,000** | **336** | **0.17 s** | **0.23 s** | **0** | **86 %** | **18.5** | **PASS** |
| 3,500 | 352 | 1.5 s | 1.8 s | 0 | 100 % | 21.8 | FAIL (CPU) |
| 4,000 | 352 | 4.0 s | 4.4 s | 0 | 100 % | 21.8 | FAIL |
| 5,000 | 353 | 7.9 s | 10.1 s | 0.2 % | 100 % | 21.7 | FAIL |
| 6,000 | 353 | 9.6 s | 13.6 s | 0.6 % | 100 % | 21.7 | FAIL |

Throughput plateaus at **≈ 350 requests/s** once the CPU is full; beyond that more students only add queueing
delay. RAM was never a constraint (Moodle container ≤ 2 GB, ≥ 30 GB free). Raising `pm.max_children` further will
not help while the CPU is saturated — more capacity needs more cores (or moving MariaDB / Jobe to another host).

## Track 2 — CodeRunner (Jobe sandbox)

Each student: opens the coding quiz, writes code for 20–40 s, submits; the answer is compiled and run against the
test cases in Jobe. Then 10–30 s pause and repeat. 3 of 4 sample answers are correct; the average mark over
32,091 graded attempts was 0.75, confirming the code really ran.

| Students coding | Jobe runs/s | Page p95 | Submit + grade p95 | Errors | Host CPU | Verdict |
|---|---|---|---|---|---|---|
| 500 | 9.9 | 0.05 s | 0.15 s | 0 | 10 % | PASS |
| 1,000 | 20.1 | 0.07 s | 0.18 s | 0 | 23 % | PASS |
| 1,500 | 30.2 | 0.08 s | 0.20 s | 0 | 38 % | PASS |
| **2,000** | **39.9** | **0.10 s** | **0.27 s** | **0** | **60 %** | **PASS** |
| 3,000 | 51.3 | 2.9 s | 8.8 s | 0 | 99 % | FAIL (CPU) |

At 3,000 the CPU split was Moodle ≈ 14 cores, Jobe ≈ 9.6, MariaDB ≈ 3.6. Jobe's own limit (16 programs at once)
was never reached. A heavier question (more test cases, slower code, compiled languages) will lower this number
roughly in proportion to the per-run CPU time; this question runs in ≈ 0.1 s.

## Track 3 — Jupyter notebooks (`mod_jupyter`)

Each student opens the activity (Moodle starts their personal Jupyter server), JupyterLab loads, a kernel starts and
runs a pandas/numpy cell, then runs a small cell every 15–30 s.

| Students | Server start p50 / p95 | Cell run p95 | Errors | JupyterHub RAM | Host RAM free | Verdict |
|---|---|---|---|---|---|---|
| 10 | 2.3 / 3.7 s | 0.05 s | 0 | 1.9 GB | 30.0 GB | PASS |
| 50 | 2.1 / 3.5 s | 0.06 s | 0 | 9.0 GB | 23.6 GB | PASS |
| 100 | 2.1 / 3.8 s | 0.05 s | 0 | 18.2 GB | 14.9 GB | PASS |
| 125 | 2.2 / 4.4 s | 0.05 s | 0 | 22.8 GB | 10.5 GB | PASS |
| 150 | 2.3 / 4.2 s | 0.05 s | 0 | 26.8 GB | **6.0 GB** | PASS (at the safety floor) |

- Memory grows linearly at **≈ 180 MB per student** (Jupyter server ≈ 120 MB + kernel with pandas ≈ 60 MB).
- CPU is not the issue (JupyterHub ≈ 0.5–0.9 cores total for these light cells).
- The test stopped at 150 because host free RAM reached the 6 GB safety floor. A practical limit on this shared
  host is **≈ 140** students with notebooks open; with the other services' memory freed, ≈ 300.
- Servers are **never shut down automatically** today, so this counts everyone who opened a notebook since the hub
  started, not just people currently active. Adding an idle culler (e.g. stop after 30 min idle) makes the limit
  apply to *active* students only.
- All students run in one container with no per-student memory limit, so one student loading a large dataset can
  exhaust RAM for everyone (see the per-student container discussion).

## Changes made during the test

| Change | Where |
|---|---|
| Apache prefork + mod_php → event MPM + PHP-FPM; worker limit 256 → 1024; 112 PHP-FPM children | `moodle-tuning/` + two lines in `docker-compose.yaml` |
| Test course `LOADTEST-2026` and 8,000 accounts `lt26_0001…8000` | Still present — remove with `setup_course.php delete` |
| 150 test Jupyter servers | Removed after Track 3 |

Invalid runs (harness or config mistakes, kept for the record): `results/*-INVALID-*`. A first attempt at the
FPM setup used `enablereuse` on the Apache→PHP-FPM proxy, which pins FPM children to idle connections and made
requests hang for 300 s; it was removed before the valid runs.

Raw data per run: `results/20260930-1928-browse`, `results/20260930-2045-coding`, `results/20260930-2110-jupyter`
(`sla.md`, `stages_report.json`, `requests-*.csv`, `monitor.csv`, Locust HTML report).
