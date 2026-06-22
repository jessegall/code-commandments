#!/usr/bin/env sh
# Helper — assemble a RESUME briefing for a FRESH context taking over in-flight
# work. The read/verify counterpart of handoff.sh: handoff.sh WRITES the snapshot,
# resume.sh READS it back, RE-VERIFIES it against the live repo (a snapshot can be
# stale), and surfaces the plan-progress memory — everything the new session needs
# in one command.
#
# It prints to stdout (it changes nothing); follow the NEXT STEPS it prints.
# Usage:  sh .claude/hooks/resume.sh
root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
handoff="$root/HANDOFF.md"

# Live repo state — the TRUTH to reconcile the (possibly stale) handoff against.
branch=$(git -C "$root" rev-parse --abbrev-ref HEAD 2>/dev/null || echo '(unknown)')
upstream=$(git -C "$root" rev-parse --abbrev-ref --symbolic-full-name '@{u}' 2>/dev/null || echo '(no upstream)')
status=$(git -C "$root" status --short 2>/dev/null)
commits=$(git -C "$root" log --oneline -15 2>/dev/null)

gate=''
if [ -f "$root/artisan" ]; then
    gate=$(cd "$root" && php artisan commandments:judge --git 2>/dev/null | tail -40)
elif [ -x "$root/vendor/bin/commandments" ]; then
    gate=$(cd "$root" && vendor/bin/commandments judge --git 2>/dev/null | tail -40)
fi

# Plan loop marker + plan-progress memory (independent of the loop being armed —
# a resuming session needs the plan even when the loop was stopped to hand off).
planactive='no'
[ -f "$root/.claude/plan-active" ] && planactive="yes (continuations: $(cat "$root/.claude/plan-active" 2>/dev/null))"

memslug=$(printf '%s' "$root" | sed 's#/#-#g')
memdir="${CLAUDE_MEMORY_DIR:-$HOME/.claude/projects/$memslug/memory}"
planfiles=$(ls "$memdir"/*progress*.md 2>/dev/null)

# printf with %s ARGUMENTS keeps gathered output literal — a diff/finding with
# backticks or $() can never execute.
printf '=== RESUME BRIEFING — %s ===\n\n' "$branch"

if [ -f "$handoff" ]; then
    printf '## HANDOFF.md (the snapshot to resume from)\n\n%s\n\n' "$(cat "$handoff" 2>/dev/null)"
else
    printf '## HANDOFF.md\n\n(none at repo root — resume from the plan-progress memory and the live state below.)\n\n'
fi

printf '## LIVE REPO — verify the handoff against THIS (the repo is the truth)\n\n'
printf -- '- Branch: `%s` -> upstream `%s`\n' "$branch" "$upstream"
printf -- '- Plan loop active: %s\n\n' "$planactive"
printf '### git status --short\n```\n%s\n```\n\n' "${status:-clean}"
printf '### Recent commits\n```\n%s\n```\n\n' "${commits:-none}"
printf '### Commandments snapshot — judge --git\n```\n%s\n```\n\n' "${gate:-(not run / clean)}"

if [ -n "$planfiles" ]; then
    printf '## Plan-progress memory (the authoritative plan)\n\n'
    for f in $planfiles; do
        printf '### %s\n```\n%s\n```\n\n' "$(basename "$f")" "$(cat "$f" 2>/dev/null)"
    done
else
    printf '## Plan-progress memory\n\n(none found in `%s`)\n\n' "$memdir"
fi

printf '## NEXT STEPS (do these now)\n'
printf '1. READ the whole handoff + plan memory above before acting.\n'
printf '2. RECONCILE it against the LIVE REPO section — if work already landed or the branch moved, trust the repo.\n'
printf '3. Create an ACTIVE TODO LIST (the TodoWrite tool) — one item per phase — and keep it live (mark each in_progress when you start it, completed when it lands) so the user can follow your progress in the terminal.\n'
printf '4. Re-arm the loop ONLY if the plan-progress memory still lists REMAINING phases AND .claude/plan-active is absent AND .claude/hooks/plan-start.sh exists: sh .claude/hooks/plan-start.sh (if the marker exists the loop is already armed).\n'
printf '5. Continue from the Next step — the handoff'\''s, or if there is no HANDOFF.md the plan-progress memory'\''s NEXT STEP. Refresh HANDOFF.md (sh .claude/hooks/handoff.sh) before any pause/compaction — a handoff is checkpoint insurance and does NOT release the loop; keep going unless the plan is DONE or you hit a genuine blocker.\n'
printf '6. When the work is fully DONE, remove the handoff so the next session starts clean: rm HANDOFF.md (it is gitignored transient state — there is nothing to git rm).\n'
