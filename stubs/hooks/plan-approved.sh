#!/usr/bin/env sh
# PostToolUse / ExitPlanMode — fires ONLY when a plan is approved
# (PostToolUse never fires when a plan is rejected). Arms the auto-continue
# loop for THIS worktree and nudges the model to drive the plan to completion.
#
# The marker lives at the WORKTREE ROOT's .claude/, so parallel git worktrees
# each get their own marker and never clobber each other's loop state.
root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
mkdir -p "$root/.claude"
printf '0' > "$root/.claude/plan-active"

cat <<'JSON'
{"hookSpecificOutput":{"hookEventName":"PostToolUse","additionalContext":"Plan approved — drive it to completion phase by phase WITHOUT stopping. FIRST, create an ACTIVE TODO LIST (the TodoWrite tool) — one item per phase — and keep it live (mark each in_progress when you start it, completed when it lands) so the user can follow your progress in the terminal. THEN arm the safety-net loop so an API hiccup that merely halts a turn can't strand the plan — run this now: /loop 15m If a plan is active (.claude/plan-active exists) and unfinished, resume it from your plan-progress memory and keep going (run the commandments gate and commit each phase); if the marker is gone, do nothing and let the loop end. After each phase: run the commandments gate (vendor/bin/commandments judge --staged, then --next --git), resolve every sin, commit, then continue. KEEP GOING until the plan is fully implemented. Only stop for a GENUINE blocker: a decision only the user can make, information you cannot find or infer, or an unrecoverable failure. The following are NOT blockers — do NOT stop for them: the turn got long, context is growing or about to compact, the next part is error-prone, it would be cleaner with fresh context, or wanting to checkpoint. To release the loop (plan DONE, or a genuine blocker) run: sh .claude/hooks/plan-release.sh \"<reason>\"  — a non-blocker reason is refused and you must keep going. Do not delete the marker by hand; that is blocked."}}
JSON
