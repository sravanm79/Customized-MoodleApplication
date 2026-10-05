# 20261005-1413-video-remote   capacity (highest consecutive passing stage): 1750 users

| users | req/s | errors | page loads p95 | video start p95 | video stalls /100 chunks | video stall s total | error rate % | swap-in pages/s | host CPU avg % | verdict |
|---|---|---|---|---|---|---|---|---|---|---|
| 1000 | 102.7 | 0 | 45 | 172 | 0.0 | 0.0 | 0.0 | 0.0 | 2.5 | PASS |
| 1500 | 169.4 | 0 | 42 | 314 | 0.0 | 0.0 | 0.0 | 0.0 | 3.3 | PASS |
| 1750 | 208.9 | 0 | 87 | 1832 | 0.0 | 0.0 | 0.0 | 0.0 | 4.1 | PASS |
| 2000 | 231.3 | 0 | 1102 | 6922 ❌ | 0.04 | 1.7 | 0.0 | 0.0 | 6.0 | FAIL |

| users | apache busy max | php-fpm procs max (/112) | db conn max | moodle_app CPU avg % | db CPU avg % | jobe CPU max % | moodle_app MiB max | locust CPU avg % | min MemAvailable GB | NIC out Mbps avg / max |
|---|---|---|---|---|---|---|---|---|---|---|
| 1000 | 8 | 64 | 9 | 30.0 | 11.0 | 0.0 | 841 | 0 | 47.2 | 475 / 687 |
| 1500 | 10 | 64 | 11 | 52.0 | 10.0 | 0.0 | 865 | 0 | 47.2 | 743 / 980 |
| 1750 | 7 | 64 | 11 | 58.0 | 10.0 | 0.0 | 951 | 0 | 47.2 | 880 / 980 |
| 2000 | 12 | 64 | 10 | 91.0 | 17.0 | 12.0 | 1227 | 0 | 46.9 | 977 / 980 |
