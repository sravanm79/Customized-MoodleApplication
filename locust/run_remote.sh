#!/usr/bin/env bash
# Like run_track.sh, but Locust runs on a second machine on the LAN, so the load crosses the real network and
# switch (the video track mostly uses bandwidth, which a load generator on the Moodle host never touches).
# Monitoring (monitor.py) and the SLA analysis stay on this, the Moodle host.
#
#   ./run_remote.sh setup                                   # once: copy files + create the venv on the load generator
#   ./run_remote.sh video "25,50,100,250,500,1000" 180
#   VIDEO_KBPS=1500 ./run_remote.sh video "100,250,500" 180  # as if lectures were 1.5 Mbps videos
#
# LOADGEN=vocab@192.168.30.121  LOADGEN_PORT=2222  PROCS=4 (Locust processes there). Needs SSH key login:
#   ssh-copy-id -p 2222 vocab@192.168.30.121
set -euo pipefail
cd "$(dirname "$0")"
LOADGEN=${LOADGEN:-vocab@192.168.30.121}; PORT=${LOADGEN_PORT:-2222}; DIR=${LOADGEN_DIR:-moodle-locust}
SSH=(ssh -p "$PORT" -o BatchMode=yes "$LOADGEN")

push() {
    rsync -az -e "ssh -p $PORT" --exclude .venv --exclude results --exclude __pycache__ --exclude '*.xlsx' \
        ./ "$LOADGEN:$DIR/"
    rsync -az -e "ssh -p $PORT" ../tls/public/iiitdwd-lms-ca.crt "$LOADGEN:$DIR/lms-ca.crt"
}

if [ "${1:-}" = setup ]; then
    push
    "${SSH[@]}" "cd $DIR && python3 -m venv .venv && .venv/bin/pip install -q locust websocket-client psutil playwright \
        && .venv/bin/locust --version && echo cpus: \$(nproc) && ulimit -Hn"
    exit 0
fi

TRACK=$1; STAGES=$2; STAGE_SECONDS=${3:-180}; PROCS=${PROCS:-4}
RUN="results/$(date +%Y%m%d-%H%M)-$TRACK-remote"; mkdir -p "$RUN"
echo "track=$TRACK stages=$STAGES stage_seconds=$STAGE_SECONDS loadgen=$LOADGEN procs=$PROCS -> $RUN"

# Stage windows (load generator clock) are matched against monitor samples (this host's clock).
skew=$(python3 -c "import sys; print(abs(float(sys.argv[1]) - $(date +%s.%N)))" "$("${SSH[@]}" date +%s.%N)")
echo "clock difference to load generator: ${skew}s"

push
# The memory guard now protects the load generator (SERVER-2 runs other work too), not Moodle's host.
ENV="TRACK=$TRACK STAGES=$STAGES STAGE_SECONDS=$STAGE_SECONDS OUT=$RUN PROCS=$PROCS SPAWN_RATE=${SPAWN_RATE:-10}"
ENV+=" MEM_GUARD_GB=${MEM_GUARD_GB:-1}"
for v in HOST VIDEO_KBPS CHUNK_KB WATCH_MIN BUFFER_S; do
    [ -n "${!v:-}" ] && ENV+=" $v=$(printf %q "${!v}")"
done

.venv/bin/python monitor.py "$RUN" 5 & MON=$!
trap 'kill $MON 2>/dev/null || true' EXIT

"${SSH[@]}" "cd $DIR && mkdir -p $RUN && ulimit -n \$(ulimit -Hn) && $ENV \
    .venv/bin/locust -f locustfile.py --headless --only-summary --processes $PROCS \
    --csv $RUN/locust --html $RUN/locust_report.html 2>&1 | tail -40" | tee "$RUN/locust_summary.txt"

kill $MON 2>/dev/null || true
rsync -az -e "ssh -p $PORT" "$LOADGEN:$DIR/$RUN/" "$RUN/"
.venv/bin/python analyze.py "$RUN" "${WARMUP:-60}" | tee "$RUN/sla.md"
