# 20261005-1355-video-remote   capacity (highest consecutive passing stage): 250 users

| users | req/s | errors | video stalls /100 chunks | video stall s total | error rate % | swap-in pages/s | host CPU avg % | page loads p95 | video start p95 | verdict |
|---|---|---|---|---|---|---|---|---|---|---|
| 50 | 19.9 | 0 | 0.0 | 0.0 | 0.0 | 0.0 | 1.8 | – | – | PASS |
| 100 | 40.4 | 0 | 0.0 | 0.0 | 0.0 | 0.0 | 2.4 | 42 | 100 | PASS |
| 250 | 91.9 | 0 | 0.0 | 0.0 | 0.0 | 0.0 | 3.7 | 42 | 417 | PASS |
| 400 | 199.5 | 0 | 74.86 ❌ | 1636.2 | 0.0 | 0.0 | 6.2 | 434 | 4062 ❌ | FAIL |

| users | apache busy max | php-fpm procs max (/112) | db conn max | moodle_app CPU avg % | db CPU avg % | jobe CPU max % | moodle_app MiB max | locust CPU avg % | min MemAvailable GB | NIC out Mbps avg / max |
|---|---|---|---|---|---|---|---|---|---|---|
| 50 | 2 | 64 | 4 | 30.0 | 6.0 | 0.0 | 835 | 0 | 46.6 | 163 / 492 |
| 100 | 3 | 64 | 5 | 17.0 | 4.0 | 0.0 | 822 | 0 | 46.9 | 335 / 416 |
| 250 | 2 | 64 | 4 | 55.0 | 11.0 | 0.0 | 855 | 0 | 46.9 | 799 / 973 |
| 400 | 8 | 64 | 12 | 63.0 | 12.0 | 0.0 | 1010 | 0 | 46.7 | 976 / 976 |
