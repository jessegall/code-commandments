#!/usr/bin/env sh
# SessionStart — clear a STALE plan-loop marker ONLY on a genuinely NEW session.
#
# Claude Code fires SessionStart with a "source": startup (a brand-new session),
# resume (--resume/--continue), compact (auto/manual context compaction), or clear
# (/clear). A previous session's `.claude/plan-active` marker persists on disk, so
# a NEW session would have its Stop hook (the active profile's stop-hook.sh) silently auto-continue a
# plan that is finished or abandoned — tripping the agent. Cross-session
# resumption is supposed to go through the handoff OFFER (handoff-detect.sh, which
# asks first), not the loop.
#
# So we remove the marker ONLY when source=startup. A resume/compact/clear keeps
# the SAME work in flight (compaction in particular MUST keep it, or a long plan
# loses its loop mid-run), so the marker is preserved in those cases.
root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
src=$(cat 2>/dev/null | sed -n 's/.*"source"[[:space:]]*:[[:space:]]*"\([a-zA-Z]*\)".*/\1/p')

# Only a brand-new session clears it; anything else (or an unreadable source)
# leaves the marker untouched so an in-flight plan is never dropped.
[ "$src" = "startup" ] && rm -f "$root/.claude/plan-active" 2>/dev/null

exit 0
