#!/usr/bin/env bash
# Judges a snapshot in the capped dev container, as judge.sh does, and fails when the run is killed — the container's
# 3 GB is the hard cap — or when its cgroup peak passes the budget scripts/memory/budgets records under <name>, so a
# change that grows the memory a real codebase costs fails CI before it reaches anyone.
# Usage: scripts/memory/gate.sh <name> <snapshot>
set -euo pipefail

name="$1"
snapshot="$2"
root="$(cd "$(dirname "$0")/../.." && pwd -P)"
budget="$(awk -v name="$name" '$1 == name { print $2 }' "$root/scripts/memory/budgets")"
[ -n "$budget" ] || { echo "no memory budget for $name in scripts/memory/budgets" >&2; exit 1; }

report="$("$root/scripts/memory/judge.sh" "$snapshot")"
echo "$report"
peak="$(awk '$1 == "peak" { print $2 }' <<< "$report")"
if [ -z "$peak" ]; then
    echo "✗ $name: the run left no peak — the container was killed past its cap" >&2
    exit 1
fi
if [ "$peak" -gt "$budget" ]; then
    echo "✗ $name: peak $peak bytes is over its budget of $budget bytes" >&2
    exit 1
fi
echo "✓ $name: peak $peak bytes, within its budget of $budget bytes"
