#!/usr/bin/env sh
# Helper — arm the plan auto-continue loop for THIS worktree on demand
# (e.g. an autonomous grind started via /loop or cron, not via plan-mode
# approval). The Stop hook (keep-going.sh) then drives the session forward
# after each turn until the plan is done or you release it with
# plan-release.sh. Run it as:  sh .claude/hooks/plan-start.sh
root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
mkdir -p "$root/.claude"
printf '0' > "$root/.claude/plan-active"

echo "Plan loop ARMED ($root/.claude/plan-active). Now arm the safety-net loop so an API stall can't strand the plan — run: /loop 15m If a plan is active (.claude/plan-active exists) and unfinished, resume it from your plan-progress memory and keep going (gate + commit each phase); if the marker is gone, let the loop end. The session will keep going after each turn until the plan is complete. Release ONLY when the plan is done or you hit a genuine blocker: sh .claude/hooks/plan-release.sh \"<reason>\". Deleting the marker by hand is blocked."
