#!/usr/bin/env sh
# SessionStart — if a HANDOFF.md is sitting at the repo root, work was left
# mid-flight. Nudge the model to OFFER the user a resume (ask first; never
# auto-resume). Emits nothing when there is no handoff, so a clean repo starts
# silent. Plain sh so it works in both Laravel and standalone consumers.
root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
[ -f "$root/HANDOFF.md" ] || exit 0

cat <<'JSON'
{"hookSpecificOutput":{"hookEventName":"SessionStart","additionalContext":"A HANDOFF.md exists at the repo root — a previous session left work mid-flight. Before anything else, ASK THE USER (do not start automatically): \"I see a handoff file from a previous session — want me to resume from it?\" If they say yes, run `sh .claude/hooks/resume.sh` and follow its briefing: read the handoff + plan-progress memory, reconcile it against the live repo (the repo is the truth), create an active todo list, re-arm the plan loop if the plan is unfinished (sh .claude/hooks/plan-start.sh), then continue from the Next step. If they say no, leave HANDOFF.md untouched and do whatever they ask instead."}}
JSON
