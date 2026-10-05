# Moodle capacity test (Locust)

Answers: **how many concurrently active students can this Moodle support on this hardware**, per workload,
with an explicit pass/fail SLA. Results and the write-up are in [`REPORT.md`](REPORT.md).

## Layout

| File | Purpose |
|---|---|
| `setup/setup_course.php` | Creates the isolated course `LOADTEST-2026` (page, forum, MCQ quiz, CodeRunner quiz, Jupyter activity) and synthetic students `lt26_0001…` (password `LoadTest#2026`). `delete` removes it all. |
| `setup/course.json` | Ids written by the setup script, read by the locustfile |
| `setup/setup_video.php` | Video track: adds a **VIDEO LECTURE** section with an uploaded `.mp4` (embedded File resource) to an existing course (default PYTHON LESSON, id 2) and enrols the same synthetic students there. `delete` removes the students, `delete-video` also the section. Output → `setup/video.json` |
| `locustfile.py` | Four tracks (`TRACK=browse|coding|jupyter|video`) + staged ramp shape + host memory guard |
| `run_remote.sh` | Runs a track with Locust on a second LAN machine (SERVER-2) and monitoring here — required for `video` |
| `browser_video_check.py` | Real Google Chrome viewers (Playwright): time to picture, freezes, dropped frames, resolution, seek time |
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
| Video: start playback / after seek p95 | < 3 s |
| Video: stalls (picture freezes) | < 1 per 100 chunks |
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

### Video track (uploaded lecture)

Bandwidth is what video uses up, so Locust must run on **another machine on the LAN** (SERVER-2, 192.168.30.121):
from the Moodle host itself the traffic never leaves the machine and the NIC (1 Gbps) is never tested.

```bash
# H.264/AAC MP4 with the index at the front (plays in every browser, starts before it is fully downloaded)
ffmpeg -i in.mp4 -c:v libx264 -crf 23 -maxrate 1500k -bufsize 3000k -vf scale=-2:720 -c:a aac -b:a 96k -movflags +faststart lecture.mp4
docker cp lecture.mp4 moodle_app:/tmp/lecture-if-elif-else.mp4 && docker cp setup/setup_video.php moodle_app:/tmp/
docker exec moodle_app su daemon -s /bin/bash -c "php /tmp/setup_video.php create /tmp/lecture-if-elif-else.mp4 1000 2" \
  | sed -n '/^{/,/^}/p' > setup/video.json   # then add "video_seconds": <duration from ffprobe>

ssh-copy-id -p 2222 vocab@192.168.30.121        # once: key login to the load generator
./run_remote.sh setup                            # once: copy files + venv there
./run_remote.sh video "25,50,100,250,500,1000" 180
VIDEO_KBPS=1500 ./run_remote.sh video "100,250,500,750" 180   # as if every lecture were a 1.5 Mbps video

# while a stage is running (and once without load, as a baseline): what students actually see
.venv/bin/python browser_video_check.py --viewers 5 --seconds 120 --seek

docker exec moodle_app su daemon -s /bin/bash -c "php /tmp/setup_video.php delete"   # students only; video stays
```

User behaviour (think times are what makes "concurrent users" meaningful):

| Track | Loop | Think time |
|---|---|---|
| browse | dashboard 2 : course 3 : reading page 3 : forum+thread 1 : MCQ quiz attempt 1 | 5–15 s between actions, 10–20 s answering the quiz |
| coding | course → coding quiz view → start → *write code 20–40 s* → submit (Jobe runs it) | 10–30 s |
| jupyter | open activity (spawns server) → load JupyterLab → start kernel → `import pandas` cell, then run cells | 15–30 s |
| video | lecture page → play 2–8 min from the start or a random seek point, fetching 1 MiB Range chunks the way a browser player does (keeps 10–30 s buffered, paced at the video bitrate); a chunk that arrives after the buffer ran dry is a stall | 5–20 s |

Only HTML pages and API calls are requested (no static JS/CSS/images, which browsers cache after the first visit).
`run_track.sh` runs the load generator on the same host as Moodle; `run_remote.sh` runs it on SERVER-2.
