#!/usr/bin/env bash
# Judges a whole snapshot with every detector inside the capped dev container (3 GB, no swap, 2 CPUs) under
# GOMEMLIMIT=3GiB, and prints how long it took, the machine's load, how many findings it made and the container's
# peak memory, as the kernel's cgroup counts it: the Go engine and the PHP, node and mypy bridges it runs, all of it.
# A run past the cap is killed by the kernel, not measured. The Roslyn bridge runs in its own capped container.
# Usage: scripts/memory/judge.sh <snapshot>
set -euo pipefail
root="$(cd "$(dirname "$0")/../.." && pwd -P)"
snapshot="$(cd "$1" && pwd -P)"
# The run's files stay inside the checkout, which is all the dev container sees, in a folder git ignores.
mkdir -p "$root/.memory-scratch"
out="$(mktemp -d "$root/.memory-scratch/judge.XXXXXX")"
trap 'rm -rf "$out"' EXIT
packages="${NUGET_PACKAGES:-$HOME/.nuget/packages}"
mounts=(--mount "$snapshot")
[ -d "$packages" ] && mounts+=(--mount "$packages")

cd "$root"
scripts/dev go build -o "$out/commandments" ./cmd/commandments
scripts/dev "${mounts[@]}" sh -c "
    start=\$(date +%s)
    NUGET_PACKAGES='$packages' GOMEMLIMIT=3GiB '$out/commandments' judge '$snapshot' --parallel=2 --no-checklist > '$out/judged' 2> '$out/warnings'
    echo \"exit \$? after \$(( \$(date +%s) - start ))s, load \$(cut -d' ' -f1-3 /proc/loadavg)\"
    echo \"peak \$(cat /sys/fs/cgroup/memory.peak) bytes\"
"
echo "findings $(grep -c 'Detector\]' "$out/judged" || true)"
if grep -q 'failed and was skipped' "$out/warnings"; then
    grep 'failed and was skipped' "$out/warnings"
fi
