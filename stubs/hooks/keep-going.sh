#!/usr/bin/env sh
# Stop hook — while a plan is active for THIS worktree, refuse to idle-stop so
# the approved plan gets driven to completion. The marker lives at the worktree
# root's .claude/, so parallel git worktrees never share loop state.
#
# This handles a CLEAN stop. An API error that merely HALTS the turn may not
# fire a Stop event, so a timed /loop (armed by plan-approved.sh / plan-start.sh)
# is the safety net that re-engages an idle-but-alive session after a stall.
#
# GUARD: each forced continuation increments a counter; after `cap` the guard
# trips, clears the marker, and yields control instead of looping forever.
root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
marker="$root/.claude/plan-active"

# No active plan for this worktree -> allow the session to stop normally.
[ -f "$marker" ] || exit 0

cap=200
count=$(cat "$marker" 2>/dev/null)
case "$count" in ''|*[!0-9]*) count=0 ;; esac

if [ "$count" -ge "$cap" ]; then
    rm -f "$marker"
    printf '{"hookSpecificOutput":{"hookEventName":"Stop","additionalContext":"Plan auto-continue guard reached %s continuations — yielding control so the session is not stuck looping. If the plan is not finished, run sh .claude/hooks/plan-start.sh to resume."}}' "$cap"
    exit 0
fi

count=$((count + 1))
printf '%s' "$count" > "$marker"

if [ -f "$root/artisan" ]; then run="php artisan commandments:"; else run="vendor/bin/commandments "; fi

msg="The approved plan is not finished yet (auto-continue $count/$cap) — continue implementing the next step now, do NOT stop. After each phase run the commandments gate (\`${run}judge --git\`), resolve every finding, and commit. These are NOT reasons to stop: the turn got long, context is growing or about to compact, the next part is error-prone, fresh context would be cleaner, or wanting to checkpoint (writing a handoff is checkpoint insurance — it does NOT release the loop) — keep going. Only for a GENUINE blocker (a decision only the user can make, information you cannot find or infer, or an unrecoverable failure) release the loop: sh .claude/hooks/plan-release.sh \"<reason>\" and explain what you need."
if command -v jq >/dev/null 2>&1; then
    jq -nc --arg r "$msg" '{decision:"block",reason:$r}'
else
    printf '{"decision":"block","reason":"The approved plan is not finished (auto-continue %s/%s). Continue now, do NOT stop. A long turn or context compaction is not a blocker. Release only for a genuine blocker via sh .claude/hooks/plan-release.sh; otherwise keep going."}' "$count" "$cap"
fi
exit 0
