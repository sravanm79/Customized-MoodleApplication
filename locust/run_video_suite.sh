#!/usr/bin/env bash
# One video load configuration end to end: Locust viewers from SERVER-2 (run_remote.sh) plus real Chrome viewers
# measuring time to picture, freezes, dropped frames and frame timing.
#   ./run_video_suite.sh <label> "<stages>" <stage_seconds> [VIDEO_KBPS]
#   ./run_video_suite.sh asis    "100,250,500,1000" 120
#   ./run_video_suite.sh hd720   "100,250,500,750"  120 1500
# BROWSER_REMOTE=1 runs the Chrome viewers on SERVER-2 too, so their video crosses the LAN like a student's.
# BROWSER_EVERY=1 runs them in every stage (results in <run>/browser/<users>/), not just the last.
# The Chrome viewers log in as lt26_<BROWSER_USER>… (default 991); keep that above the largest stage.
set -euo pipefail
cd "$(dirname "$0")"
LABEL=$1; STAGES=$2; SECS=$3; KBPS=${4:-}
BUSER=${BROWSER_USER:-991}; BSECS=${BROWSER_SECONDS:-60}
IFS=, read -ra ST <<< "$STAGES"; n=${#ST[@]}
SSH=(ssh -p "${LOADGEN_PORT:-2222}" -o BatchMode=yes "${LOADGEN:-vocab@192.168.30.121}")
TMP="results/browser-$LABEL-$$"
first=$(( ${BROWSER_EVERY:-0} == 1 ? 0 : n - 1 ))

browser() {  # $1 = stage index: start 45 s into that stage (after its ramp-up)
    local users=${ST[$1]} delay=$(( $1 * SECS + 45 ))
    sleep "$delay"
    local cmd=".venv/bin/python browser_video_check.py --viewers 5 --seconds $BSECS --seek --first-user $BUSER --out $TMP/$users"
    if [ "${BROWSER_REMOTE:-0}" = 1 ]; then
        "${SSH[@]}" "cd moodle-locust && $cmd" > "/tmp/browser-$LABEL-$users.log" 2>&1
    else
        $cmd > "/tmp/browser-$LABEL-$users.log" 2>&1
    fi
}
BR=()
for ((i = first; i < n; i++)); do browser "$i" & BR+=($!); done
VIDEO_KBPS=$KBPS ./run_remote.sh video "$STAGES" "$SECS"
for p in "${BR[@]}"; do wait "$p" || true; done
RUN=$(ls -td results/*-video-remote | head -1)
mv "$RUN" "${RUN}-$LABEL"; RUN="${RUN}-$LABEL"
if [ "${BROWSER_REMOTE:-0}" = 1 ]; then
    mkdir -p "$TMP"
    rsync -az -e "ssh -p ${LOADGEN_PORT:-2222}" "${LOADGEN:-vocab@192.168.30.121}:moodle-locust/$TMP/" "$TMP/" || true
fi
[ -d "$TMP" ] && mv "$TMP" "$RUN/browser"
for ((i = first; i < n; i++)); do
    cp "/tmp/browser-$LABEL-${ST[$i]}.log" "$RUN/browser/${ST[$i]}.log" 2>/dev/null || true
done
echo "RESULTS: $RUN"
