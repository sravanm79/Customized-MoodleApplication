# 20261005-1338-video-remote   capacity (highest consecutive passing stage): 1000 users

| users | req/s | errors | video stalls /100 chunks | video stall s total | error rate % | swap-in pages/s | host CPU avg % | page loads p95 | video start p95 | verdict |
|---|---|---|---|---|---|---|---|---|---|---|
| 100 | 3.3 | 0 | 0.0 | 0.0 | 0.0 | 0.0 | 0.9 | – | – | PASS |
| 250 | 5.7 | 0 | 0.0 | 0.0 | 0.0 | 0.0 | 1.0 | 44 | 181 | PASS |
| 500 | 18.0 | 0 | 0.0 | 0.0 | 0.0 | 0.0 | 1.8 | 187 | 347 | PASS |
| 1000 | 48.8 | 0 | 0.0 | 0.0 | 0.0 | 0.1 | 3.7 | 52 | 1182 | PASS |

| users | apache busy max | php-fpm procs max (/112) | db conn max | moodle_app CPU avg % | db CPU avg % | jobe CPU max % | moodle_app MiB max | locust CPU avg % | min MemAvailable GB | NIC out Mbps avg / max |
|---|---|---|---|---|---|---|---|---|---|---|
| 100 | 6 | 32 | 9 | 17.0 | 4.0 | 0.0 | 485 | 0 | 47.7 | 34 / 172 |
| 250 | 2 | 37 | 5 | 3.0 | 1.0 | 0.0 | 538 | 0 | 47.6 | 125 / 373 |
| 500 | 9 | 44 | 10 | 15.0 | 2.0 | 0.0 | 577 | 0 | 47.4 | 207 / 398 |
| 1000 | 7 | 88 | 9 | 29.0 | 5.0 | 0.0 | 863 | 0 | 45.9 | 452 / 804 |
