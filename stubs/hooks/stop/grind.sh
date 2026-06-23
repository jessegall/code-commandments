#!/usr/bin/env sh
# Stop hook — GRIND profile. Heads-down: drive the approved plan to completion,
# committing each phase. NEVER run judge/tests between phases — the single
# reckon is the pre-push gate (issue #197). Continuation is plan-driven by the
# worktree-keyed .claude/plan-active marker; with no active plan there is
# nothing to keep going for, so the Stop is allowed (grind still never judges).
cat >/dev/null 2>&1   # drain the Stop payload

root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
marker="$root/.claude/plan-active"

# No active plan for this worktree -> allow the stop (and never judge).
[ -f "$marker" ] || exit 0

cap=200
count=$(cat "$marker" 2>/dev/null)
case "$count" in ''|*[!0-9]*) count=0 ;; esac

if [ "$count" -ge "$cap" ]; then
    rm -f "$marker"
    printf '{"hookSpecificOutput":{"hookEventName":"Stop","additionalContext":"Plan auto-continue guard reached %s continuations — yielding so the session is not stuck looping. If the plan is not finished, run sh .claude/hooks/plan-start.sh to resume."}}' "$cap"
    exit 0
fi

count=$((count + 1))
printf '%s' "$count" > "$marker"

msg="The approved plan is not finished (grind, auto-continue $count/$cap) — implement the next phase now and commit it. Do NOT run judge or tests between phases: grind defers the reckon to the single pre-push gate. These are NOT reasons to stop: the turn got long, context is growing or about to compact, the next part is error-prone, or wanting to checkpoint. Release the loop ONLY for a genuine blocker (a decision only the user can make, information you cannot find, or an unrecoverable failure): sh .claude/hooks/plan-release.sh \"<reason>\"."
if command -v jq >/dev/null 2>&1; then
    jq -nc --arg r "$msg" '{decision:"block",reason:$r}'
else
    printf '{"decision":"block","reason":"The approved plan is not finished (grind %s/%s). Implement + commit the next phase now; do NOT judge between phases (the pre-push gate is the only reckon). Release only for a genuine blocker via sh .claude/hooks/plan-release.sh."}' "$count" "$cap"
fi
exit 0
