#!/usr/bin/env sh
# Helper — scaffold a comprehensive HANDOFF.md at the repo root so a FRESH
# context (a new session, a teammate, a revived plan after a stall) can take
# over cold with zero archaeology.
#
# It AUTO-FILLS the mechanical snapshot (branch, status, recent commits, the
# uncommitted diff stat, the commandments gate, the active plan + its progress
# memory) and leaves a >>> TODO <<< template for the model to complete with the
# narrative. Run it, then EDIT HANDOFF.md to fill every >>> TODO <<<.
#
# Usage:  sh .claude/hooks/handoff.sh
root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
out="$root/HANDOFF.md"

branch=$(git -C "$root" rev-parse --abbrev-ref HEAD 2>/dev/null || echo '(unknown)')
upstream=$(git -C "$root" rev-parse --abbrev-ref --symbolic-full-name '@{u}' 2>/dev/null || echo '(no upstream)')
status=$(git -C "$root" status --short 2>/dev/null)
diffstat=$(git -C "$root" diff --stat 2>/dev/null; git -C "$root" diff --cached --stat 2>/dev/null)
commits=$(git -C "$root" log --oneline -15 2>/dev/null)

# Commandments snapshot of the current changes (best-effort; artisan for a Laravel
# app, else the standalone binary — same runner order as the rest of the suite).
gate=''
if [ -f "$root/artisan" ]; then
    gate=$(cd "$root" && php artisan commandments:judge --git 2>/dev/null | tail -40)
elif [ -x "$root/vendor/bin/commandments" ]; then
    gate=$(cd "$root" && vendor/bin/commandments judge --git 2>/dev/null | tail -40)
fi

# Active plan loop marker (continuation count), if armed.
planactive='no'
[ -f "$root/.claude/plan-active" ] && planactive="yes (continuations: $(cat "$root/.claude/plan-active" 2>/dev/null))"

# The plan-progress MEMORY file(s) — surfaced whether or not the loop is active.
# A stalled or released plan still has its progress memory, and a cold context
# needs it MOST then; gating this on the loop marker (the old bug) hid the plan
# exactly when handoff matters. The file-based memory lives in the per-project
# memory dir (CLAUDE.md), encoded as the absolute repo path with '/'→'-'.
memslug=$(printf '%s' "$root" | sed 's#/#-#g')
memdir="${CLAUDE_MEMORY_DIR:-$HOME/.claude/projects/$memslug/memory}"
planfiles=$(ls "$memdir"/*progress*.md 2>/dev/null)

# printf with %s ARGUMENTS keeps gathered output literal — never re-interpreted
# (a diff/finding containing backticks or $() can't execute).
{
    printf '# Handoff — %s\n\n' "$branch"
    printf '_Auto-scaffolded by `.claude/hooks/handoff.sh`. The snapshot below is gathered automatically; every `>>> TODO <<<` section MUST be completed by you before this is a real handoff._\n\n'

    printf '## Snapshot (auto-gathered)\n\n'
    printf -- '- **Branch:** `%s` → upstream `%s`\n' "$branch" "$upstream"
    printf -- '- **Plan loop active:** %s\n' "$planactive"
    if [ -n "$planfiles" ]; then
        printf -- '- **Plan progress memory:**\n'
        for f in $planfiles; do printf -- '    - `%s`\n' "$f"; done
        printf '\n'
    else
        printf -- '- **Plan progress memory:** none found in `%s`\n\n' "$memdir"
    fi

    # The plan-progress memory is the plan itself — include it VERBATIM so a cold
    # context resumes from it even when the loop was stopped to write this handoff.
    if [ -n "$planfiles" ]; then
        for f in $planfiles; do
            printf '### Plan progress — `%s`\n```\n%s\n```\n\n' "$(basename "$f")" "$(cat "$f" 2>/dev/null)"
        done
    fi

    printf '### Working tree — `git status --short`\n```\n%s\n```\n\n' "${status:-clean}"
    printf '### Uncommitted diff — `git diff --stat`\n```\n%s\n```\n\n' "${diffstat:-none}"
    printf '### Recent commits\n```\n%s\n```\n\n' "${commits:-none}"
    printf '### Commandments snapshot — `judge --git`\n```\n%s\n```\n\n' "${gate:-(not run / clean)}"

    printf '## Goal\n\n>>> TODO: one or two lines on what this work delivers. <<<\n\n'
    printf '## State\n\n- **Done:** >>> TODO: completed pieces, each with its commit sha. <<<\n- **In progress:** >>> TODO <<<\n- **Remaining (ordered):** >>> TODO <<<\n\n'
    printf '## Next step\n\n>>> TODO: the single exact next action to take on resume. <<<\n\n'
    printf '## Decisions & deferrals\n\n>>> TODO: choices made, and anything deferred (with why). <<<\n\n'
    printf '## Resume notes\n\n>>> TODO: key files, gotchas, and how to verify (tests / gate command). <<<\n'
} > "$out"

echo "Wrote $out — the snapshot is auto-filled. NOW open it and COMPLETE every >>> TODO <<< section (goal, done/in-progress/remaining, next step, decisions, resume notes) so a cold context can take over. Read the gathered snapshot, write the narrative, keep it accurate."
