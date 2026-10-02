# 20260930-2110-jupyter   capacity (highest consecutive passing stage): 150 users

| users | req/s | errors | jupyter cell p95 | error rate % | swap-in pages/s | host CPU avg % | verdict |
|---|---|---|---|---|---|---|---|
| 10 | 0.4 | 0 | 50 | 0.0 | 0.0 | 0.5 | PASS |
| 25 | 1.1 | 0 | 61 | 0.0 | 0.0 | 0.5 | PASS |
| 50 | 2.2 | 0 | 57 | 0.0 | 0.0 | 0.6 | PASS |
| 75 | 3.4 | 0 | 57 | 0.0 | 0.0 | 0.7 | PASS |
| 100 | 4.4 | 0 | 54 | 0.0 | 0.0 | 0.9 | PASS |
| 125 | 5.6 | 0 | 52 | 0.0 | 0.0 | 1.1 | PASS |
| 150 | 7.7 | 0 | 53 | 0.0 | 0.0 | 0.9 | PASS |

| users | apache busy max | php-fpm procs max (/112) | db conn max | moodle_app CPU avg % | db CPU avg % | jobe CPU max % | moodle_app MiB max | locust CPU avg % | min MemAvailable GB |
|---|---|---|---|---|---|---|---|---|---|
| 10 | 1 | 64 | 4 | 0.0 | 0.0 | 0.0 | 1226 | 0 | 30.4 |
| 25 | 1 | 64 | 4 | 1.0 | 1.0 | 0.0 | 1191 | 0 | 27.9 |
| 50 | 1 | 64 | 4 | 0.0 | 0.0 | 0.0 | 1241 | 1 | 23.6 |
| 75 | 1 | 64 | 4 | 0.0 | 1.0 | 0.0 | 1193 | 1 | 19.6 |
| 100 | 1 | 64 | 4 | 1.0 | 1.0 | 0.0 | 1187 | 1 | 14.9 |
| 125 | 2 | 64 | 4 | 1.0 | 1.0 | 0.0 | 1041 | 1 | 10.5 |
| 150 | 1 | 64 | 4 | 1.0 | 0.0 | 0.0 | 856 | 1 | 6.0 |
