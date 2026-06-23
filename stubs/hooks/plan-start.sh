#!/usr/bin/env sh
# Helper — arm the plan auto-continue loop for THIS worktree on demand
# (e.g. an autonomous grind started via /loop or cron, not via plan-mode
# approval). The Stop hook (the active profile's stop-hook.sh) then drives the session forward
# after each turn until the plan is done or you release it with
# plan-release.sh. Run it as:  sh .claude/hooks/plan-start.sh
root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
mkdir -p "$root/.claude"
printf '0' > "$root/.claude/plan-active"

if [ -f "$root/artisan" ]; then run="php artisan commandments:"; else run="vendor/bin/commandments "; fi

echo "Plan loop ARMED ($root/.claude/plan-active). Now create an ACTIVE TODO LIST (the TodoWrite tool) — one item per phase — and keep it live (mark each in_progress when you start it, completed when it lands) so the user can follow your progress in the terminal. Then arm the safety-net loop so an API stall can't strand the plan — run: /loop 15m \"If a plan is active (.claude/plan-active exists) and unfinished, resume it from your plan-progress memory and keep going — run the commandments gate and commit each phase; if the marker is gone, do nothing and let the loop end.\" After each phase run the commandments gate (\`${run}judge --git\`), resolve every finding, and commit; the session keeps going after each turn until the plan is complete. These are NOT reasons to stop: the turn got long, context is growing or about to compact, the next part is error-prone, fresh context would be cleaner, or wanting to checkpoint (writing a handoff is checkpoint insurance — it does NOT release the loop). Release ONLY when the plan is done or you hit a genuine blocker (a decision only the user can make, information you cannot find or infer, or an unrecoverable failure): sh .claude/hooks/plan-release.sh \"<reason>\". Deleting the marker by hand is blocked."
