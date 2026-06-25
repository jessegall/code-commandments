#!/usr/bin/env sh
# PostToolUse(Bash) — after a `git commit`, drive the post-phase routine.
#
#   - ALWAYS: a commit means a phase just landed — walk and resolve every finding
#     with `pilgrimage`/`next` before the next phase.
#   - WHILE A PLAN IS ACTIVE for this worktree (the plan-active marker exists):
#     ALSO refresh the plan-progress MEMORY, so completed phases and remaining
#     work survive context compression and the plan can be resumed from memory.
payload=$(cat)

# React only to git commits.
printf '%s' "$payload" | grep -q "git commit" || exit 0

root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")

# Resolve the runner the way the rest of the suite does: artisan when this is a
# Laravel app (published config + provider bindings), else the standalone binary.
if [ -f "$root/artisan" ]; then run="php artisan commandments:"; else run="vendor/bin/commandments "; fi

ctx="A commit just landed — a phase is complete. Act as a sin resolver now: run \`${run}pilgrimage\` then \`${run}next\` to walk the findings (one prophet at a time, with its full scripture and every location — READ each output IN FULL, never truncate it). Handle every finding before starting the next phase. Fix each sin — even pre-existing ones in files you touched. Advisories: default to FIXING; absolve only when the rubric LEAVE-WHEN genuinely applies, with a reason. Absolve is not a dismiss button. I did not cause this is never a reason to leave a sin in place."

# Only nudge the plan-progress memory while THIS worktree's plan loop is armed,
# so ordinary non-plan commits stay quiet.
if [ -f "$root/.claude/plan-active" ]; then
    ctx="$ctx  PLAN ACTIVE — this phase is committed: UPDATE YOUR PLAN-PROGRESS MEMORY now so the plan SURVIVES CONTEXT COMPRESSION and you can resume from it after a summary. HOW: keep exactly ONE memory file for this plan in your project memory dir (the file-based memory system described in CLAUDE.md) — name it <plan-slug>-progress.md, with frontmatter (name, description, metadata.type: project) — and UPDATE IT IN PLACE every phase (never create a second file); keep a one-line pointer to it in MEMORY.md. WHAT TO WRITE (rewrite the body each time so it reflects the CURRENT state, newest-first): (1) GOAL — one line on what the plan delivers; (2) BRANCH — the working branch + base; (3) PHASES — a checklist: each DONE phase with its commit short-sha, the CURRENT phase, then REMAINING phases in order; (4) NEXT STEP — the exact next action to take on resume; (5) DECISIONS / DEFERRALS — choices made and anything deferred (with why); (6) RESUME NOTES — key files, gotchas, and how to verify (tests/gate). Convert any relative dates to absolute. Do this after EVERY committed phase, not only at the end."
fi

if command -v jq >/dev/null 2>&1; then
    jq -nc --arg ctx "$ctx" '{hookSpecificOutput:{hookEventName:"PostToolUse",additionalContext:$ctx}}'
else
    # jq absent: emit the always-on sin-resolver nudge only. The runner goes in via
    # a printf %s slot; the rest of the body has no % or backslash to reprocess.
    printf '{"hookSpecificOutput":{"hookEventName":"PostToolUse","additionalContext":"A commit just landed — walk the findings with %spilgrimage then next (read each output IN FULL), resolving every one before the next phase."}}' "$run"
fi

exit 0
