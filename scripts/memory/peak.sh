#!/usr/bin/env bash
# Runs a command under the agent limits (GOMEMLIMIT=3GiB, GOMAXPROCS=2) and prints its peak memory footprint: the
# physical memory it holds, compressed pages included. macOS compresses a busy process's pages rather than keep them
# resident, so its resident set size undercounts; the footprint does not. The command is killed once its footprint
# passes 4 GiB, and says so: the machine is shared, and a run that needs more is a finding, not a reason to let it grow.
# Usage: scripts/memory/peak.sh <command> [args...]
set -uo pipefail
limit=4096
report="$(mktemp)"
trap 'rm -f "$report"' EXIT

# footprint is the process's physical footprint in MB.
footprint() {
    /usr/bin/footprint "$1" 2> /dev/null | awk '/Footprint:/ {
        for (i = 1; i <= NF; i++) if ($i == "Footprint:") { value = $(i + 1); unit = $(i + 2) }
        if (unit == "GB") value *= 1024
        if (unit == "KB") value /= 1024
        printf "%d", value
    }'
}

GOMEMLIMIT=3GiB GOMAXPROCS=2 /usr/bin/time -l "$@" 2> >(tee "$report" >&2) &
timer=$!
killed=""
while kill -0 "$timer" 2> /dev/null; do
    for child in $(pgrep -P "$timer"); do
        held="$(footprint "$child")"
        if [ -n "$held" ] && [ "$held" -gt "$limit" ]; then
            kill "$child"
            killed="killed at $held MB, past the 4 GiB limit"
        fi
    done
    sleep 1
done
wait "$timer"
status=$?
sleep 0.2

peak="$(awk '/peak memory footprint/ {print $1}' "$report")"
echo "peak footprint $((peak / 1024 / 1024)) MiB (exit $status)${killed:+, $killed}"
