#!/usr/bin/env bash
# Judges a snapshot with both tools — the PHP tool and the Go binary built from this checkout — each once, at
# --parallel=2, inside the capped dev container, and prints the findings only one of them makes, as `file:line
# [Detector]`. A real codebase is the parity check; a snapshot (scripts/memory/snapshot.sh) keeps the live checkout out
# of it. The run's files stay in a folder git ignores.
# Usage: scripts/parity.sh <snapshot> [out-dir]
set -euo pipefail
export LC_ALL=C

root="$(cd "$(dirname "$0")/.." && pwd -P)"
snapshot="$(cd "$1" && pwd -P)"
out="${2:-$root/.memory-scratch/parity.$(basename "$snapshot")}"
mkdir -p "$out"
out="$(cd "$out" && pwd -P)"

cd "$root"
scripts/dev env GOMEMLIMIT=3GiB GOMAXPROCS=2 CGO_ENABLED=0 go build -o "$out/commandments" ./cmd/commandments
scripts/dev --mount "$snapshot" php bin/commandments-php judge "$snapshot" --parallel=2 --checklist="$out/php.md" > "$out/php.log" 2>&1 || true
scripts/dev --mount "$snapshot" env GOMEMLIMIT=3GiB GOMAXPROCS=2 "$out/commandments" judge "$snapshot" --parallel=2 --checklist="$out/go.md" > "$out/go.log" 2>&1 || true

# findings is a checklist's findings, one `file:line [Detector]` per line, sorted.
findings() {
    grep -o '`[^`]*:[0-9]*`.*\[[A-Za-z]*\]' "$1" | sed -E 's/^`([^`]*)`.*(\[[A-Za-z]*\])$/\1 \2/' | sort -u
}

findings "$out/php.md" > "$out/php.txt"
findings "$out/go.md" > "$out/go.txt"
echo "php $(wc -l < "$out/php.txt")  go $(wc -l < "$out/go.txt")  (in $out)"
{ diff "$out/php.txt" "$out/go.txt" || true; } | grep '^[<>]' | sed -e 's/^</only PHP:/' -e 's/^>/only Go: /' || echo "no difference"
