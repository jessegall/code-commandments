#!/usr/bin/env sh
# SessionStart — if a previous session left work mid-flight (a HANDOFF.md at the
# repo root, OR a *-progress plan memory in the project memory dir), nudge the
# model to OFFER the user a resume (ask first; never auto-resume). Emits nothing
# when there is neither, so a clean repo starts silent. Plain sh so it works in
# both Laravel and standalone consumers.
root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")

# Resolve the file-based memory dir the same way handoff.sh / resume.sh do.
memslug=$(printf '%s' "$root" | sed 's#/#-#g')
memdir="${CLAUDE_MEMORY_DIR:-$HOME/.claude/projects/$memslug/memory}"
progress=$(ls "$memdir"/*progress*.md 2>/dev/null | head -1)

# Nothing to resume → stay silent.
[ -f "$root/HANDOFF.md" ] || [ -n "$progress" ] || exit 0

if [ -f "$root/HANDOFF.md" ]; then
    src="a HANDOFF.md at the repo root"
else
    src="an unfinished plan-progress memory (no HANDOFF.md)"
fi

ctx="A previous session left work mid-flight — $src. Before anything else, ASK THE USER (do not start automatically): \"I see a handoff/plan from a previous session — want me to resume from it?\" If they say yes, run \`sh .claude/hooks/resume.sh\` and follow its briefing: read the handoff + plan-progress memory, reconcile it against the live repo (the repo is the truth), create an ACTIVE TODO LIST (the TodoWrite tool) — one item per phase — and keep it live so the user can follow your progress in the terminal, re-arm the plan loop only if the plan-progress memory still lists REMAINING phases AND .claude/plan-active is absent AND .claude/hooks/plan-start.sh exists (sh .claude/hooks/plan-start.sh), then continue from the Next step. If they say no, leave everything untouched and do whatever they ask instead."

if command -v jq >/dev/null 2>&1; then
    jq -nc --arg ctx "$ctx" '{hookSpecificOutput:{hookEventName:"SessionStart",additionalContext:$ctx}}'
else
    printf '{"hookSpecificOutput":{"hookEventName":"SessionStart","additionalContext":"A previous session left work mid-flight (%s). ASK THE USER whether to resume before doing anything; if yes run sh .claude/hooks/resume.sh and follow its briefing (ask first, never auto-resume)."}}' "$src"
fi
