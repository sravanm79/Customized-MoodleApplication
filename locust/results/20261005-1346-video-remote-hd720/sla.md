# 20261005-1346-video-remote   capacity (highest consecutive passing stage): 500 users

| users | req/s | errors | video stalls /100 chunks | video stall s total | error rate % | swap-in pages/s | host CPU avg % | page loads p95 | video start p95 | verdict |
|---|---|---|---|---|---|---|---|---|---|---|
| 100 | 17.9 | 0 | 0.0 | 0.0 | 0.0 | 0.0 | 1.2 | – | – | PASS |
| 250 | 45.7 | 0 | 0.0 | 0.0 | 0.0 | 0.0 | 1.7 | 46 | 95 | PASS |
| 500 | 93.7 | 0 | 0.0 | 0.0 | 0.0 | 0.0 | 4.2 | 46 | 205 | PASS |
| 750 | 177.8 | 0 | 60.43 ❌ | 4765.1 | 0.0 | 0.0 | 10.4 | 778 | 7614 ❌ | FAIL |

| users | apache busy max | php-fpm procs max (/112) | db conn max | moodle_app CPU avg % | db CPU avg % | jobe CPU max % | moodle_app MiB max | locust CPU avg % | min MemAvailable GB | NIC out Mbps avg / max |
|---|---|---|---|---|---|---|---|---|---|---|
| 100 | 1 | 64 | 4 | 29.0 | 6.0 | 0.0 | 765 | 0 | 47.3 | 154 / 346 |
| 250 | 1 | 64 | 4 | 20.0 | 4.0 | 0.0 | 754 | 0 | 47.3 | 399 / 435 |
| 500 | 7 | 64 | 5 | 58.0 | 12.0 | 0.0 | 785 | 0 | 46.3 | 802 / 949 |
| 750 | 18 | 64 | 10 | 73.0 | 12.0 | 0.0 | 1215 | 0 | 44.0 | 976 / 977 |
