#!/usr/bin/env bash
# Compares what the PHP tool and the Go frontend find in the .vue and .ts files under one path, a whole project
# included: prints the findings only one of them has.
# Usage: scripts/frontend-parity.sh <path> [out-dir]
set -euo pipefail
export LC_ALL=C
root="$(cd "$(dirname "$0")/.." && pwd)"
path="$(cd "$1" && pwd -P)"
out="${2:-$(mktemp -d)}"
bin/commandments-php judge "$path" --ignore-package-requirements --checklist="$out/php.md" > /dev/null 2>&1 || true
grep -o '`[^`]*:[0-9]*`.*\[[A-Za-z]*\]' "$out/php.md" | sed -E 's/^`([^`]*)`.*(\[[A-Za-z]*\])$/\1 \2/' | grep -E '\.(vue|ts):[0-9]+ ' | sort -u > "$out/php.txt"
(cd "$root" && scripts/dev --mount "$path" go run ./engine/frontend/parity "$path") | sort -u > "$out/go.txt"
echo "php $(wc -l < "$out/php.txt")  go $(wc -l < "$out/go.txt")  (in $out)"
{ diff "$out/php.txt" "$out/go.txt" || true; } | grep "^[<>]" || echo "no difference"
