"""Sample host + container health every INTERVAL seconds into OUT/monitor.csv until killed.

Columns: host CPU %, MemAvailable, swap used, swap-in/out pages/s, load, per-container CPU %/MiB,
Apache busy workers (of 256), MariaDB connections, number of Jupyter single-user servers.
"""
import csv
import json
import os
import subprocess
import sys
import time

import psutil

OUT = sys.argv[1]
INTERVAL = float(sys.argv[2]) if len(sys.argv) > 2 else 5
CONTAINERS = ["moodle_app", "moodle_db", "moodle_jobe", "moodle_jupyterhub"]
os.makedirs(OUT, exist_ok=True)


def sh(cmd):
    return subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=30).stdout


def apache_busy():
    php = ('$c=stream_context_create(["http"=>["header"=>"Host: status.localhost\\r\\n"]]);'
           '$s=@file_get_contents("http://127.0.0.1:8080/server-status?auto",false,$c);'
           'preg_match("/BusyWorkers: (\\d+)/",$s,$b);preg_match("/IdleWorkers: (\\d+)/",$s,$i);echo ($b[1]??-1)," ",($i[1]??-1);')
    out = sh(f"docker exec moodle_app php -r '{php}'").split()
    return (int(out[0]), int(out[1])) if len(out) == 2 else (-1, -1)


def db_conns():
    out = sh("docker exec moodle_db mariadb -ubn_moodle -p"$MOODLE_DB_PASSWORD" -N -e "
             "\"SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected','Threads_running')\" 2>/dev/null").split()
    d = dict(zip(out[0::2], out[1::2]))
    return int(d.get("Threads_connected", -1)), int(d.get("Threads_running", -1))


def docker_stats():
    res = {}
    for line in sh("docker stats --no-stream --format '{{json .}}' " + " ".join(CONTAINERS)).splitlines():
        j = json.loads(line)
        mem = j["MemUsage"].split("/")[0].strip()
        mib = float(mem[:-3]) * {"KiB": 1 / 1024, "MiB": 1, "GiB": 1024}.get(mem[-3:], 1)
        res[j["Name"]] = (float(j["CPUPerc"].rstrip("%")), mib)
    return res


_procs = {}


def locust_cpu():
    """CPU % (100 = one core) used by the Locust load generator processes on this host."""
    total = 0.0
    for p in psutil.process_iter(["pid", "cmdline"]):
        if "bin/locust" in " ".join(p.info["cmdline"] or []):
            proc = _procs.setdefault(p.info["pid"], p)
            try:
                total += proc.cpu_percent()
            except psutil.Error:
                pass
    return round(total, 1)


def main():
    f = open(os.path.join(OUT, "monitor.csv"), "w", newline="")
    w = csv.writer(f)
    cols = ["ts", "host_cpu_pct", "mem_avail_gb", "swap_used_gb", "swap_in_pps", "swap_out_pps", "load1",
            "apache_busy", "apache_idle", "fpm_procs", "db_conn", "db_running", "jupyter_servers", "locust_cpu"]
    for c in CONTAINERS:
        cols += [f"{c}_cpu", f"{c}_mib"]
    w.writerow(cols)
    psutil.cpu_percent()
    last = psutil.swap_memory()
    last_t = time.time()
    while True:
        time.sleep(INTERVAL)
        now = time.time()
        sw = psutil.swap_memory()
        dt = now - last_t
        sin = (sw.sin - last.sin) / 4096 / dt
        sout = (sw.sout - last.sout) / 4096 / dt
        last, last_t = sw, now
        busy, idle = apache_busy()
        fpm = int(sh("docker exec moodle_app pgrep -fc 'php-fpm: pool'").strip() or 0)
        conn, running = db_conns()
        servers = int(sh("docker exec moodle_jupyterhub pgrep -fc jupyterhub-singleuser").strip() or 0)
        ds = docker_stats()
        row = [f"{now:.0f}", psutil.cpu_percent(), round(psutil.virtual_memory().available / 2**30, 2),
               round(sw.used / 2**30, 2), round(sin, 1), round(sout, 1), os.getloadavg()[0],
               busy, idle, fpm, conn, running, servers, locust_cpu()]
        for c in CONTAINERS:
            row += list(ds.get(c, (-1, -1)))
        w.writerow(row)
        f.flush()


if __name__ == "__main__":
    main()
