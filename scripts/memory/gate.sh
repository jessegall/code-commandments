#!/usr/bin/env bash
# Judges a snapshot in the capped dev container, as judge.sh does, and fails when the run is killed — the container's
# 3 GB is the hard cap — or when its cgroup peak passes the budget scripts/memory/budgets records under <name>, so a
# change that grows the memory a real codebase costs fails before a release. Run by hand (CONTRIBUTING.md).
# Usage: scripts/memory/gate.sh <name> <snapshot>
set -euo pipefail

name="$1"
snapshot="$2"
root="$(cd "$(dirname "$0")/../.." && pwd -P)"
budget="$(awk -v name="$name" '$1 == name { print $2 }' "$root/scripts/memory/budgets")"
[ -n "$budget" ] || { echo "no memory budget for $name in scripts/memory/budgets" >&2; exit 1; }

# macOS clears old files out of /tmp and leaves the folders, and a judge over an empty snapshot passes any budget.
if [ -z "$(find "$snapshot" -type f -not -name '.snapshot-commit' -print -quit)" ]; then
    echo "✗ $name: $snapshot holds no files; make it again with scripts/memory/snapshot.sh" >&2
    exit 1
fi

report="$("$root/scripts/memory/judge.sh" "$snapshot")"
echo "$report"
if [ "$(awk '$1 == "findings" { print $2 }' <<< "$report")" = "0" ]; then
    echo "✗ $name: the run judged nothing; a real codebase always holds a finding" >&2
    exit 1
fi
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
