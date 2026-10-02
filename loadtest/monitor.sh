#!/bin/bash
# Samples host + Moodle container CPU/RAM every 5s into $1 (CSV) until killed.
# cpu_pct columns are docker's % of ONE core (2400% = 24 cores busy).
out=${1:-monitor.csv}
echo "ts,host_cpu_busy_pct,host_load1,app_cpu_pct,app_mem_mib,db_cpu_pct,db_mem_mib,httpd_procs" > "$out"
prev=($(head -1 /proc/stat))
while true; do
  sleep 5
  cur=($(head -1 /proc/stat))
  idle=$(( (cur[4]+cur[5]) - (prev[4]+prev[5]) )); tot=0
  for i in 1 2 3 4 5 6 7 8; do tot=$((tot + cur[i] - prev[i])); done
  busy=$(awk -v i=$idle -v t=$tot 'BEGIN{printf "%.1f",100*(t-i)/t}'); prev=("${cur[@]}")
  st=$(docker stats --no-stream --format '{{.Name}} {{.CPUPerc}} {{.MemUsage}}' moodle_app moodle_db)
  tomib='function m(v){ if (v ~ /GiB/) {sub("GiB","",v); return v*1024} sub("MiB","",v); return v+0 }'
  app=$(echo "$st" | awk "$tomib"'/moodle_app/{gsub("%","",$2); printf "%s,%.0f", $2, m($3)}')
  db=$(echo "$st"  | awk "$tomib"'/moodle_db/{gsub("%","",$2); printf "%s,%.0f", $2, m($3)}')
  fpm=$(docker top moodle_app -o pid,comm | grep -c httpd)
  echo "$(date +%T),$busy,$(cut -d' ' -f1 /proc/loadavg),$app,$db,$fpm" >> "$out"
done
