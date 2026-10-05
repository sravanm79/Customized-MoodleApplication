# Video streaming load test: Special Session 02_PyTorch Basics_1080p.mp4 (uploaded by teacher)

How many students can watch an uploaded lecture video at the same time, by video quality. Load from a second LAN machine (SERVER-2) with Locust viewers that stream the file in HTTP Range chunks paced at the bitrate, as a browser player does (10–30 s buffer, half the sittings seek); real Chrome viewers measured what students see during the busiest stage. 2 min per stage, first 60 s of each stage excluded.

## The video

| Property | Value |
|---|---|
| File | Special Session 02_PyTorch Basics_1080p.mp4 (uploaded by teacher) |
| Size | 140.9 MB (147,723,624 bytes) |
| Length | 44 min 0 s |
| Picture | 1720×1080, 25 fps, H.264 (314 kbps) |
| Sound | AAC 128 kbps |
| Total bitrate | **448 kbps** (≈ 192 MB per viewer per hour) |
| Fast start (index at the front) | Yes: playback starts before the file is downloaded |

## Results by quality

| Quality | Bitrate | Same 44-min lecture would be | Data per viewer-hour | Concurrent viewers OK | Fails at | Real Chrome viewers (busiest stage) |
|---|---|---|---|---|---|---|
| As uploaded | 0.448 Mbps | 141 MB | 192 MB | **1750** | 2000 | 1720x1080, 0 freezes, 0 dropped frames, page 1307 ms, seek 38 ms (Moodle host, not over the LAN) |
| 720p HD | 1.5 Mbps | 472 MB | 644 MB | **500** | 750 | 1720x1080, 0 freezes, 0 dropped frames, page 1313 ms, seek 36 ms (Moodle host, not over the LAN) |
| 1080p Full HD | 3 Mbps | 944 MB | 1287 MB | **250** | 400 | 1720x1080, 0 freezes, 0 dropped frames, page 6761 ms, seek 684 ms (SERVER-2, over the LAN) |

## Maximum simultaneous viewers (video as uploaded)

Ramped 1000 → 2000 viewers, 2.5 min per stage, until it failed. **1750 viewers pass; it fails at 2000.**

Server side (all Locust viewers):

| Viewers | Verdict | Freezes /100 chunks | Frozen s total | Video start p95 | Chunk first byte p50 / p95 | 1 MB chunk p50 / p95 | Lecture page p95 | Errors | Network out avg / max | Host CPU | PHP workers (/112) | Apache busy |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1000 | PASS | 0.0 | 0.0 | 172 ms | 9 / 17 ms | 162 / 209 ms | 45 ms | 0.0 % | 475 / 687 Mbps | 2.5 % | 64 | 8 |
| 1500 | PASS | 0.0 | 0.0 | 317 ms | 8 / 16 ms | 156 / 321 ms | 42 ms | 0.0 % | 743 / 980 Mbps | 3.3 % | 64 | 10 |
| 1750 | PASS | 0.0 | 0.0 | 1940 ms | 12 / 28 ms | 405 / 2064 ms | 87 ms | 0.0 % | 880 / 980 Mbps | 4.1 % | 64 | 7 |
| 2000 | FAIL | 0.04 | 1.7 | 7118 ms | 55 / 773 ms | 4796 / 7149 ms | 1102 ms | 0.0 % | 977 / 980 Mbps | 6.0 % | 64 | 12 |

What a student sees (5 real Chrome viewers on SERVER-2, over the LAN, during each stage; frame timing from `requestVideoFrameCallback`, which fires for every frame painted on screen):

| Viewers | Page load avg | Freezes (frozen s) | Frame gap p95 / worst | Late frames (> 80 ms) | Frames skipped | Furthest behind real time | Seek avg |
|---|---|---|---|---|---|---|---|
| 1000 | 1429 ms | 0 (0.0 s) | 66.6 / 83 ms | 3 (0.04 %) | 0 | 31 ms | 57 ms |
| 1500 | 1295 ms | 0 (0.0 s) | 66.6 / 67 ms | 0 (0.0 %) | 0 | 24 ms | 41 ms |
| 1750 | 1331 ms | 0 (0.0 s) | 66.7 / 67 ms | 0 (0.0 %) | 0 | 8 ms | 48 ms |
| 2000 | 6140 ms | 0 (0.0 s) | 66.7 / 67 ms | 0 (0.0 %) | 0 | 28 ms | 858 ms |

How to read the frame columns: the lecture is 25 fps, and headless Chrome paints at about 30 Hz, so frames normally arrive 33 or 67 ms apart (idle baseline: p95 67 ms, worst 83 ms, behind real time < 50 ms). A *late frame* (> 80 ms gap) is a visible stutter; *behind real time* is how much playback fell behind the clock in 60 s of watching (freezes and stutters added up). *Chunk first byte* is how long the server takes to start answering a video request (queueing in Apache/PHP + Moodle's checks), before any data flows.

## Every stage

| Quality | Viewers | Verdict | Freezes /100 chunks | Video start p95 | Chunk p95 | Errors | Network out avg / max | Host CPU | PHP workers (/112) |
|---|---|---|---|---|---|---|---|---|---|
| As uploaded | 100 | PASS | 0.0 | – ms | 96 ms | 0.0 % | 34 / 172 Mbps | 0.9 % | 32 |
| As uploaded | 250 | PASS | 0.0 | 181 ms | 145 ms | 0.0 % | 125 / 373 Mbps | 1.0 % | 37 |
| As uploaded | 500 | PASS | 0.0 | 348 ms | 340 ms | 0.0 % | 207 / 398 Mbps | 1.8 % | 44 |
| As uploaded | 1000 | PASS | 0.0 | 1746 ms | 2011 ms | 0.0 % | 452 / 804 Mbps | 3.7 % | 88 |
| 720p HD | 100 | PASS | 0.0 | – ms | 92 ms | 0.0 % | 154 / 346 Mbps | 1.2 % | 64 |
| 720p HD | 250 | PASS | 0.0 | 155 ms | 148 ms | 0.0 % | 399 / 435 Mbps | 1.7 % | 64 |
| 720p HD | 500 | PASS | 0.0 | 216 ms | 256 ms | 0.0 % | 802 / 949 Mbps | 4.2 % | 64 |
| 720p HD | 750 | FAIL | 60.43 | 7614 ms | 8625 ms | 0.0 % | 976 / 977 Mbps | 10.4 % | 64 |
| 1080p Full HD | 50 | PASS | 0.0 | – ms | 96 ms | 0.0 % | 163 / 492 Mbps | 1.8 % | 64 |
| 1080p Full HD | 100 | PASS | 0.0 | 100 ms | 99 ms | 0.0 % | 335 / 416 Mbps | 2.4 % | 64 |
| 1080p Full HD | 250 | PASS | 0.0 | 417 ms | 617 ms | 0.0 % | 799 / 973 Mbps | 3.7 % | 64 |
| 1080p Full HD | 400 | FAIL | 74.86 | 4120 ms | 3986 ms | 0.0 % | 976 / 976 Mbps | 6.2 % | 64 |

Pass = video start p95 < 3 s, < 1 freeze per 100 chunks, errors < 1 %, host CPU < 90 %, no swapping.

## What this means

- **The limit is the network, not the server.** Every failure happened when the 1 Gbps link was full (976 Mbps out); host CPU stayed under 11 %, memory and the database were idle, and there were no errors. Usable capacity ≈ **800 Mbps ÷ bitrate**: as uploaded (448 kbps) **measured: 1,750 pass, 2,000 fail** (see Maximum simultaneous viewers); 1.5 Mbps ≈ 500; 3 Mbps ≈ 250.
- **Quality (bitrate) decides how many can watch at once.** Doubling the bitrate halves the viewers. This lecture at 448 kbps already looks sharp at 1720×1080 (slides change slowly), so re-encoding it at a higher bitrate would cost capacity without a visible gain.
- **Size and length** do not change how many can watch at once; they set storage and total data: size = bitrate × length (this 44-min lecture: ~141 MB at 0.448 Mbps, ~472 MB at 1.5 Mbps, ~944 MB at 3 Mbps), and every student watching the whole lecture downloads its full size. Long lectures mean more seeks and resumes, which the test included (half the sittings seek).
- **When the link is full, everyone suffers:** viewers freeze, start-up takes 4–8 s, and even ordinary pages slow down (the lecture page took 6.7 s for Chrome viewers on SERVER-2 at 400 × 3 Mbps).
- **PHP workers:** Moodle streams files through PHP. Worker processes peaked at 88 of 112 in the first run and stayed at the 64-process idle pool in the maximum run (2,000 viewers), so PHP never ran out; it would be the next limit after a faster network link.

## Recommendations

1. Upload lectures as **H.264 MP4 with fast start, 720p or 1080p at about 0.5–1.5 Mbps** (screen recordings compress very well). Command: `ffmpeg -i in.mp4 -c:v libx264 -crf 23 -maxrate 1500k -bufsize 3000k -vf scale=-2:1080 -c:a aac -b:a 96k -movflags +faststart lecture.mp4`.
2. For more simultaneous viewers: a 10 Gbps (or bonded 2×1 Gbps) network link, or host big videos outside Moodle (e.g. YouTube unlisted / Google Drive embeds).
3. Plan for the worst moment, e.g. a whole batch opening the same lecture before an exam: viewers × bitrate must stay under ~800 Mbps.

## Caveats

- The 250- and 500-viewer stages of the *as uploaded* run overlapped another load test on the same machines (13:40–13:44); they still passed, so the result is conservative, but their network figures include that other traffic. The 1,000-viewer stage was clean.
- The higher-quality runs simulate bitrate by pacing the same file; real 720p/1080p files of that bitrate behave the same on the network and server.
- The maximum run was planned up to 3,000 viewers; the load generator (SERVER-2, which also runs other work) ran low on memory as the 2,250 stage began and stopped itself. Moodle had already failed at 2,000 with the link full, and more viewers cannot get more than the 1 Gbps link, so higher stages would only fail harder.
- In the first two runs the real Chrome viewers ran on the Moodle host itself, so they did not feel the network limit; in the 1080p run they ran on SERVER-2.

Raw data: `results/20261005-1338-video-remote-asis`, `results/20261005-1346-video-remote-hd720`, `results/20261005-1355-video-remote-hd1080`.
