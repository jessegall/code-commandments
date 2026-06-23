#!/usr/bin/env sh
# PreToolUse(Bash) — block any attempt to DELETE the plan-active loop marker
# directly. The loop may only be released via plan-release.sh, which refuses
# non-blocker reasons. This stops the model from dropping the loop just because
# a turn got long / context is compacting / the next part looks error-prone
# (none of which are blockers). The 200-continuation cap in the profile's stop-hook.sh is
# the ultimate backstop, so this can never wedge a session permanently.
payload=$(cat)
cmd=$(printf '%s' "$payload" | jq -r '.tool_input.command // empty' 2>/dev/null)
[ -n "$cmd" ] || cmd=$(printf '%s' "$payload" | tr '\n' ' ')

# The sanctioned release path is always allowed.
case "$cmd" in
    *plan-release.sh*) exit 0 ;;
esac

# Only react to commands that NAME the marker AND try to delete/move it. A
# truncation (`> marker`) does not release the loop (the file still exists), so
# only real deletions (rm / unlink / mv) are blocked.
case "$cmd" in
    *plan-active*)
        if printf '%s' "$cmd" | grep -Eq '\brm\b|\bunlink\b|\bmv\b'; then
            reason="Direct removal of the plan-loop marker is blocked. The auto-continue loop must run until the plan is DONE or you hit a GENUINE blocker: a decision only the user can make, information you cannot find or infer, or an unrecoverable failure. These are NOT blockers: the turn got long, context is growing or about to compact, the next part is error-prone, fresh context would be cleaner, or wanting to checkpoint — keep going. To release legitimately run: sh .claude/hooks/plan-release.sh \"<reason>\"  (a non-blocker reason is refused)."
            if command -v jq >/dev/null 2>&1; then
                jq -nc --arg r "$reason" '{hookSpecificOutput:{hookEventName:"PreToolUse",permissionDecision:"deny",permissionDecisionReason:$r}}'
            else
                printf '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"Direct removal of the plan-loop marker is blocked. Release only via sh .claude/hooks/plan-release.sh for a genuine blocker; a long turn or context compaction is not a blocker."}}'
            fi
            exit 0
        fi
        ;;
esac

exit 0
