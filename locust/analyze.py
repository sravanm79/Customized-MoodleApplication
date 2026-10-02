"""Per-stage SLA verdicts from requests.csv + monitor.csv.   usage: analyze.py <results dir> [warmup seconds]

SLA (all must hold for a stage to PASS):
  page loads (login, pages, quiz view/start)       p95 < 2500 ms
  graded submissions (quiz submit & grade)         p95 < 5000 ms
  jupyter: open activity (spawn)                   p95 < 10000 ms
  jupyter: run cell                                p95 < 5000 ms
  error rate                                       < 1 %
  host not swapping (avg swap-in)                  < 50 pages/s
  host not CPU-pegged (avg host CPU)               < 90 %
"""
import csv
import glob
import json
import os
import sys
from collections import defaultdict

OUT = sys.argv[1]
WARMUP = float(sys.argv[2]) if len(sys.argv) > 2 else 60

LIMITS = [  # (label, predicate on request name, p95 limit ms)
    ("page loads", lambda n: n.startswith(("page:", "login:")) or n.endswith((": view", ": start attempt"))
     or n == "jupyter: load JupyterLab", 2500),
    ("graded submit", lambda n: n.endswith("submit & grade"), 5000),
    ("jupyter spawn", lambda n: n == "jupyter: open activity (spawn server)", 10000),
    ("jupyter cell", lambda n: n == "jupyter: run cell", 5000),
]


def pct(values, p):
    if not values:
        return None
    v = sorted(values)
    return v[min(len(v) - 1, int(round(p / 100 * (len(v) - 1))))]


stages = [(float(t), int(u)) for t, u in csv.reader(open(os.path.join(OUT, "stages.csv")))]
reqs = [r for f in sorted(glob.glob(os.path.join(OUT, "requests*.csv"))) for r in csv.DictReader(open(f))]
mon = list(csv.DictReader(open(os.path.join(OUT, "monitor.csv")))) if os.path.exists(os.path.join(OUT, "monitor.csv")) else []
end_ts = max(float(r["ts"]) for r in reqs) if reqs else 0

report = []
for i, (start, users) in enumerate(stages):
    stop = stages[i + 1][0] if i + 1 < len(stages) else end_ts
    lo = start + WARMUP
    rows = [r for r in reqs if lo <= float(r["ts"]) < stop]
    if not rows:
        continue
    by_name = defaultdict(list)
    errors = sum(1 for r in rows if r["ok"] == "0")
    for r in rows:
        by_name[r["name"]].append(float(r["ms"]))
    checks = []
    for label, pred, limit in LIMITS:
        vals = [float(r["ms"]) for r in rows if pred(r["name"]) and r["ok"] == "1"]
        if vals:
            p95 = pct(vals, 95)
            checks.append({"check": f"{label} p95", "value": round(p95), "limit": limit, "pass": p95 < limit})
    err_rate = errors / len(rows) * 100
    checks.append({"check": "error rate %", "value": round(err_rate, 2), "limit": 1, "pass": err_rate < 1})
    m = [x for x in mon if lo <= float(x["ts"]) < stop]
    host = {}
    if m:
        avg = lambda k: sum(float(x[k]) for x in m) / len(m)  # noqa: E731
        mx = lambda k: max(float(x[k]) for x in m)  # noqa: E731
        host = {
            "host_cpu_avg": round(avg("host_cpu_pct"), 1), "host_cpu_max": round(mx("host_cpu_pct"), 1),
            "mem_avail_min_gb": round(min(float(x["mem_avail_gb"]) for x in m), 1),
            "swap_in_avg": round(avg("swap_in_pps"), 1),
            "apache_busy_max": int(mx("apache_busy")), "fpm_max": int(mx("fpm_procs")) if "fpm_procs" in m[0] else -1, "db_conn_max": int(mx("db_conn")),
            "jupyter_servers": int(mx("jupyter_servers")),
            "moodle_app_cpu_avg": round(avg("moodle_app_cpu"), 0), "moodle_db_cpu_avg": round(avg("moodle_db_cpu"), 0),
            "jobe_cpu_max": round(mx("moodle_jobe_cpu"), 0), "jupyterhub_mib_max": round(mx("moodle_jupyterhub_mib")),
            "moodle_app_mib_max": round(mx("moodle_app_mib")),
            "locust_cpu_avg": round(avg("locust_cpu")) if "locust_cpu" in m[0] else -1,
        }
        checks.append({"check": "swap-in pages/s", "value": host["swap_in_avg"], "limit": 50,
                       "pass": host["swap_in_avg"] < 50})
        checks.append({"check": "host CPU avg %", "value": host["host_cpu_avg"], "limit": 90,
                       "pass": host["host_cpu_avg"] < 90})
    report.append({
        "users": users, "window_s": round(stop - lo), "requests": len(rows), "rps": round(len(rows) / (stop - lo), 1),
        "errors": errors, "pass": all(c["pass"] for c in checks), "checks": checks, "host": host,
        "per_request": {n: {"count": len(v), "p50": round(pct(v, 50)), "p95": round(pct(v, 95)),
                            "p99": round(pct(v, 99)), "max": round(max(v))} for n, v in sorted(by_name.items())},
    })

json.dump(report, open(os.path.join(OUT, "stages_report.json"), "w"), indent=2)
passing = [s["users"] for s in report if s["pass"]]
capacity = 0
for s in report:  # highest stage reached while every earlier stage also passed
    if not s["pass"]:
        break
    capacity = s["users"]
print(f"# {os.path.basename(OUT)}   capacity (highest consecutive passing stage): {capacity} users\n")
names = []
for s in report:
    names += [c["check"] for c in s["checks"] if c["check"] not in names]
print("| users | req/s | errors | " + " | ".join(names) + " | verdict |")
print("|" + "---|" * (4 + len(names)))
for s in report:
    by = {c["check"]: c for c in s["checks"]}
    cells = [(f"{by[n]['value']}{'' if by[n]['pass'] else ' ❌'}" if n in by else "–") for n in names]
    print(f"| {s['users']} | {s['rps']} | {s['errors']} | " + " | ".join(cells) + f" | {'PASS' if s['pass'] else 'FAIL'} |")
if report and report[0]["host"]:
    print("\n| users | apache busy max | php-fpm procs max (/112) | db conn max | moodle_app CPU avg % | db CPU avg % | jobe CPU max % "
          "| moodle_app MiB max | locust CPU avg % | min MemAvailable GB |")
    print("|---|---|---|---|---|---|---|---|---|---|")
    for s in report:
        h = s["host"]
        print(f"| {s['users']} | {h['apache_busy_max']} | {h['fpm_max']} | {h['db_conn_max']} | {h['moodle_app_cpu_avg']} | "
              f"{h['moodle_db_cpu_avg']} | {h['jobe_cpu_max']} | {h['moodle_app_mib_max']} | {h['locust_cpu_avg']} | "
              f"{h['mem_avail_min_gb']} |")
