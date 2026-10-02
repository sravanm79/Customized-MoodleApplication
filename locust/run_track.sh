#!/usr/bin/env bash
# Run one track as a staged ramp with host monitoring, then print per-stage SLA verdicts.
#   ./run_track.sh browse  "25,50,100,250,500,750,1000"  180
#   ./run_track.sh coding  "10,25,50,100,200"            180
#   ./run_track.sh jupyter "10,25,50,75,100"             180
set -euo pipefail
cd "$(dirname "$0")"
TRACK=$1; STAGES=$2; STAGE_SECONDS=${3:-180}
RUN="results/$(date +%Y%m%d-%H%M)-$TRACK"; mkdir -p "$RUN"
echo "track=$TRACK stages=$STAGES stage_seconds=$STAGE_SECONDS -> $RUN"

.venv/bin/python monitor.py "$RUN" 5 & MON=$!
trap 'kill $MON 2>/dev/null || true' EXIT

PROCS=${PROCS:-1}
TRACK=$TRACK STAGES=$STAGES STAGE_SECONDS=$STAGE_SECONDS OUT=$RUN SPAWN_RATE=${SPAWN_RATE:-10} PROCS=$PROCS \
  .venv/bin/locust -f locustfile.py --headless --only-summary --processes "$PROCS" \
  --csv "$RUN/locust" --html "$RUN/locust_report.html" 2>&1 | tail -40 | tee "$RUN/locust_summary.txt"

kill $MON 2>/dev/null || true
.venv/bin/python analyze.py "$RUN" "${WARMUP:-60}" | tee "$RUN/sla.md"
