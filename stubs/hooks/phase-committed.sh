#!/usr/bin/env sh
# PostToolUse(Bash) — after a `git commit`, drive the post-phase routine.
#
#   - ALWAYS: a commit means a phase just landed — re-read the Code Commandments
#     and resolve every sin before the next phase.
#   - WHILE A PLAN IS ACTIVE for this worktree (the plan-active marker exists):
#     ALSO refresh the plan-progress MEMORY, so completed phases and remaining
#     work survive context compression and the plan can be resumed from memory.
payload=$(cat)

# React only to git commits.
printf '%s' "$payload" | grep -q "git commit" || exit 0

root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")

ctx="A commit just landed — a phase is complete. Re-read the Code Commandments section of CLAUDE.md now and act as a sin resolver: run \`vendor/bin/commandments judge --next --git\` and handle every finding before starting the next phase. Fix each sin — even pre-existing ones in files you touched. Warnings: default to FIXING; absolve only when the rubric LEAVE-WHEN genuinely applies, with a reason. Absolve is not a dismiss button. I did not cause this is never a reason to leave a sin in place."

# Only nudge the plan-progress memory while THIS worktree's plan loop is armed,
# so ordinary non-plan commits stay quiet.
if [ -f "$root/.claude/plan-active" ]; then
    ctx="$ctx  PLAN ACTIVE — this phase is committed: UPDATE YOUR PLAN-PROGRESS MEMORY now so the plan SURVIVES CONTEXT COMPRESSION and you can resume from it after a summary. HOW: keep exactly ONE memory file for this plan in your project memory dir (the file-based memory system described in CLAUDE.md) — name it <plan-slug>-progress.md, with frontmatter (name, description, metadata.type: project) — and UPDATE IT IN PLACE every phase (never create a second file); keep a one-line pointer to it in MEMORY.md. WHAT TO WRITE (rewrite the body each time so it reflects the CURRENT state, newest-first): (1) GOAL — one line on what the plan delivers; (2) BRANCH — the working branch + base; (3) PHASES — a checklist: each DONE phase with its commit short-sha, the CURRENT phase, then REMAINING phases in order; (4) NEXT STEP — the exact next action to take on resume; (5) DECISIONS / DEFERRALS — choices made and anything deferred (with why); (6) RESUME NOTES — key files, gotchas, and how to verify (tests/gate). Convert any relative dates to absolute. Do this after EVERY committed phase, not only at the end."
fi

if command -v jq >/dev/null 2>&1; then
    jq -nc --arg ctx "$ctx" '{hookSpecificOutput:{hookEventName:"PostToolUse",additionalContext:$ctx}}'
else
    # jq absent: emit the always-on sin-resolver nudge only (no special chars).
    printf '{"hookSpecificOutput":{"hookEventName":"PostToolUse","additionalContext":"A commit just landed — re-read the Code Commandments and run vendor/bin/commandments judge --next --git, resolving every finding before the next phase."}}'
fi

exit 0
