# Usage: python3 analyze.py <prefix>   e.g. results/C_tuned1000  -> per-minute throughput/latency + resource peaks
import csv, gzip, sys
from collections import defaultdict
p = sys.argv[1]
rows = [r for r in csv.DictReader(gzip.open(p + '_raw.csv.gz', 'rt')) if r['metric_name'] in ('http_req_duration', 'vus')]
t0 = min(int(r['timestamp']) for r in rows)
lat, vus = defaultdict(list), defaultdict(int)
for r in rows:
    m = (int(r['timestamp']) - t0) // 60
    if r['metric_name'] == 'vus': vus[m] = max(vus[m], int(float(r['metric_value'])))
    elif r['name'] != 'ajax_poll': lat[m].append(float(r['metric_value']))
import datetime
mon = list(csv.DictReader(open(p + '_monitor.csv')))
day = datetime.datetime.fromtimestamp(t0).date()
for x in mon:  # monitor ts is local HH:MM:SS -> minutes since k6 start
    x['_m'] = (datetime.datetime.combine(day, datetime.time.fromisoformat(x['ts'])).timestamp() - t0) // 60
def mm(i):
    return [x for x in mon if x['_m'] == i] or mon[-1:]
print(f"{'min':>3} {'VUs':>5} {'pages/s':>7} {'p50ms':>6} {'p95ms':>6} {'hostCPU%':>8} {'appCPU%':>7} {'appMiB':>6} {'dbCPU%':>6} {'httpd':>5}")
for m in sorted(lat):
    v = sorted(lat[m]); w = mm(m)
    mx = lambda c: max(float(x[c]) for x in w)
    print(f"{m:3d} {vus[m]:5d} {len(v)/60:7.1f} {v[len(v)//2]:6.0f} {v[int(len(v)*.95)]:6.0f} {mx('host_cpu_busy_pct'):8.1f} {mx('app_cpu_pct'):7.0f} {mx('app_mem_mib'):6.0f} {mx('db_cpu_pct'):6.0f} {mx('httpd_procs' if 'httpd_procs' in w[0] else 'fpm_procs'):5.0f}")
