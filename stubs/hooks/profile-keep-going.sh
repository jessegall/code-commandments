#!/usr/bin/env sh
# Stop hook — keep the agent working until the ACTIVE code-commandments profile's
# goal is met, THEN allow the stop. Self-completing (judge righteous => stop) and
# capped so it can never hard-trap: a finding leaves the count whether you FIX it
# or, for a genuine false positive, ABSOLVE/REPORT it.
#
#   grind            -> no SINS on the branch        (judge --branch)
#   phased|sins-only -> no findings in your changes  (judge --next --git)
#   penance          -> no findings anywhere         (judge --next)
#   disabled/other   -> no keep-going (allow stop)
cat >/dev/null 2>&1   # drain the Stop payload

root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
profile=$(cat "$root/.commandments/profile" 2>/dev/null | tr -d '[:space:]')

case "$profile" in
    grind)            args="judge --branch --no-cache" ;;
    phased|sins-only) args="judge --next --git --no-cache" ;;
    penance)          args="judge --next --no-cache" ;;
    *)                exit 0 ;;
esac

if [ -f "$root/artisan" ]; then run="php artisan commandments:"; else run="vendor/bin/commandments "; fi

# Run the profile's "are we done?" check (exit 0 = clean = may stop).
( cd "$root" && eval "${run}${args}" ) >/dev/null 2>&1
status=$?

mkdir -p "$root/.commandments" 2>/dev/null
countfile="$root/.commandments/keep-going-count"

if [ "$status" -eq 0 ]; then
    rm -f "$countfile"
    exit 0
fi

cap=200
count=$(cat "$countfile" 2>/dev/null)
case "$count" in ''|*[!0-9]*) count=0 ;; esac

if [ "$count" -ge "$cap" ]; then
    rm -f "$countfile"
    printf '{"hookSpecificOutput":{"hookEventName":"Stop","additionalContext":"Keep-going cap (%s) reached for the %s profile — yielding. Findings may still remain; run %sjudge to check."}}' "$cap" "$profile" "$run"
    exit 0
fi

count=$((count + 1))
printf '%s' "$count" > "$countfile"

reason=$(printf 'Findings remain in scope for the %s profile — keep going, do NOT stop. Resolve every finding: fix it, or for a genuine false positive absolve/report it. See what is left with %sjudge. This hook releases automatically once judge is righteous.' "$profile" "$run")
printf '{"decision":"block","reason":"%s"}' "$reason"
exit 0
