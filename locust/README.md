# Moodle capacity test (Locust)

Answers: **how many concurrently active students can this Moodle support on this hardware**, per workload,
with an explicit pass/fail SLA. Results and the write-up are in [`REPORT.md`](REPORT.md).

## Layout

| File | Purpose |
|---|---|
| `setup/setup_course.php` | Creates the isolated course `LOADTEST-2026` (page, forum, MCQ quiz, CodeRunner quiz, Jupyter activity) and synthetic students `lt26_0001…` (password `LoadTest#2026`). `delete` removes it all. |
| `setup/course.json` | Ids written by the setup script, read by the locustfile |
| `locustfile.py` | Three tracks (`TRACK=browse|coding|jupyter`) + staged ramp shape + host memory guard |
| `monitor.py` | Samples host CPU/RAM/swap, container CPU/RAM, Apache busy workers, DB connections, Jupyter servers every 5 s |
| `analyze.py` | Per-stage SLA verdicts (first 60 s of each stage excluded as ramp-up) |
| `run_track.sh` | Runs one track end to end |
| `results/<timestamp>-<track>/` | `requests.csv` (every request), `monitor.csv`, `sla.md`, `stages_report.json`, Locust HTML/CSV |

## SLA (a stage passes only if all hold)

| Check | Limit |
|---|---|
| Page loads p95 (login, pages, quiz view/start, JupyterLab load) | < 2.5 s |
| Graded submission p95 (quiz submit, incl. CodeRunner/Jobe run) | < 5 s |
| Jupyter: open activity / spawn server p95 | < 10 s |
| Jupyter: run a cell p95 | < 5 s |
| Error rate | < 1 % |
| Host swap-in (avg) | < 50 pages/s |
| Host CPU (avg) | < 90 % |

The reported capacity is the **highest stage where it and every lower stage passed**.

## Run

```bash
cd locust
python3 -m venv .venv && .venv/bin/pip install locust websocket-client psutil
docker cp setup/setup_course.php moodle_app:/tmp/ && \
  docker exec moodle_app su daemon -s /bin/bash -c "php /tmp/setup_course.php create 1000" | sed -n '/^{/,/^}/p' > setup/course.json

./run_track.sh browse  "25,50,100,250,500,750,1000" 180
./run_track.sh coding  "10,25,50,100,200"           180
./run_track.sh jupyter "10,25,50,75,100"            180   # stops early if host MemAvailable < 6 GB

# cleanup
docker exec moodle_app su daemon -s /bin/bash -c "php /tmp/setup_course.php delete"
```

User behaviour (think times are what makes "concurrent users" meaningful):

| Track | Loop | Think time |
|---|---|---|
| browse | dashboard 2 : course 3 : reading page 3 : forum+thread 1 : MCQ quiz attempt 1 | 5–15 s between actions, 10–20 s answering the quiz |
| coding | course → coding quiz view → start → *write code 20–40 s* → submit (Jobe runs it) | 10–30 s |
| jupyter | open activity (spawns server) → load JupyterLab → start kernel → `import pandas` cell, then run cells | 15–30 s |

Only HTML pages and API calls are requested (no static JS/CSS/images, which browsers cache after the first visit).
The load generator runs on the same host as Moodle.
