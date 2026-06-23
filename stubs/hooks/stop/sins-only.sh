#!/usr/bin/env sh
# Stop hook — SINS-ONLY profile. Same as phased — keep going until the findings
# in YOUR changes are resolved (judge --next --git) — but the profile suppresses
# warnings, so only SINS surface and gate. If an approved plan is active, drive
# it to completion too. Self-completing + capped.
cat >/dev/null 2>&1   # drain the Stop payload

root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
if [ -f "$root/artisan" ]; then run="php artisan commandments:"; else run="vendor/bin/commandments "; fi
marker="$root/.claude/plan-active"
mkdir -p "$root/.commandments" 2>/dev/null
cap=200

# 1) Plan drive — while an approved plan is active, keep implementing.
if [ -f "$marker" ]; then
    count=$(cat "$marker" 2>/dev/null)
    case "$count" in ''|*[!0-9]*) count=0 ;; esac

    if [ "$count" -ge "$cap" ]; then
        rm -f "$marker"
        printf '{"hookSpecificOutput":{"hookEventName":"Stop","additionalContext":"Plan auto-continue guard reached %s continuations — yielding. If the plan is not finished, run sh .claude/hooks/plan-start.sh to resume."}}' "$cap"
        exit 0
    fi

    count=$((count + 1))
    printf '%s' "$count" > "$marker"

    msg="The approved plan is not finished (sins-only, auto-continue $count/$cap) — implement the next phase now. After each phase run the gate (${run}judge --git), resolve every SIN, and commit. A long turn or context compaction is not a blocker. Release only for a genuine blocker via sh .claude/hooks/plan-release.sh \"<reason>\"."
    if command -v jq >/dev/null 2>&1; then
        jq -nc --arg r "$msg" '{decision:"block",reason:$r}'
    else
        printf '{"decision":"block","reason":"Plan not finished (sins-only %s/%s). Implement the next phase; judge --git + resolve sins + commit each phase. Release only for a genuine blocker via sh .claude/hooks/plan-release.sh."}' "$count" "$cap"
    fi
    exit 0
fi

# 2) Profile gate — keep going until your changes are sin-free.
( cd "$root" && eval "${run}judge --next --git --no-cache" ) >/dev/null 2>&1
status=$?

countfile="$root/.commandments/keep-going-count"

if [ "$status" -eq 0 ]; then
    rm -f "$countfile"
    exit 0
fi

count=$(cat "$countfile" 2>/dev/null)
case "$count" in ''|*[!0-9]*) count=0 ;; esac

if [ "$count" -ge "$cap" ]; then
    rm -f "$countfile"
    printf '{"hookSpecificOutput":{"hookEventName":"Stop","additionalContext":"Keep-going cap (%s) reached for the sins-only profile — yielding. Sins may still remain; run %sjudge --git to check."}}' "$cap" "$run"
    exit 0
fi

count=$((count + 1))
printf '%s' "$count" > "$countfile"

reason=$(printf 'Sins remain in your changes (sins-only) — keep going, do NOT stop. Resolve every one: fix it, or for a genuine false positive absolve/report it. See what is left with %sjudge --next --git. This hook releases automatically once your changes are righteous.' "$run")
printf '{"decision":"block","reason":"%s"}' "$reason"
exit 0
