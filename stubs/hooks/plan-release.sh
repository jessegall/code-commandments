#!/usr/bin/env sh
# Helper — the ONLY sanctioned way to release the plan auto-continue loop.
# Refuses an empty or non-blocker reason so the loop can't be dropped just
# because a turn got long or context is compacting. Run it as:
#   sh .claude/hooks/plan-release.sh "<reason>"
reason="$*"

if [ -z "$reason" ]; then
    echo "REFUSED: a reason is required. Release ONLY when the plan is DONE or you hit a genuine blocker — a decision only the user can make, information you cannot find or infer, or an unrecoverable failure." >&2
    exit 1
fi

low=$(printf '%s' "$reason" | tr '[:upper:]' '[:lower:]')

release() {
    root=$(git rev-parse --show-toplevel 2>/dev/null || printf '%s' "${CLAUDE_PROJECT_DIR:-.}")
    rm -f "$root/.claude/plan-active"
    echo "Plan loop RELEASED. Reason: $reason"
    exit 0
}

# Plan completion / a real blocker always releases (even if the summary text
# happens to mention a banned word). Multi-word patterns are quoted so the
# space is part of the pattern.
case "$low" in
    *done*|*complete*|*finished*|*shipped*|*blocker*|*deadlock*|*credential*|*unrecoverable*) release ;;
    *'user must'*|*'needs the user'*|*'cannot find'*|*'permission denied'*|*'only the user'*) release ;;
esac

# Otherwise reject the common non-blocker rationalizations and keep looping.
case "$low" in
    *context*|*compact*|*summar*|*token*|*turn*|*enormous*|*grown*|*grew*|*checkpoint*|*fresh*|*risky*|*later*|*resume*|*pause*|*break*|*tired*|*errorprone*|*error-prone*)
        echo "REFUSED: \"$reason\" is not a genuine blocker — the plan loop stays ARMED, keep going. Genuine blockers: a decision only the user can make, information you cannot find/infer, or an unrecoverable failure. If the PLAN IS DONE, pass a reason containing 'done' or 'complete'." >&2
        exit 1 ;;
    *'too long'*|*'should check'*|*'come back'*|*'next session'*|*'continue later'*|*'fresh context'*)
        echo "REFUSED: \"$reason\" is not a genuine blocker — the plan loop stays ARMED, keep going. Genuine blockers: a decision only the user can make, information you cannot find/infer, or an unrecoverable failure. If the PLAN IS DONE, pass a reason containing 'done' or 'complete'." >&2
        exit 1 ;;
esac

# A specific, non-blacklisted reason — treat as a genuine blocker and release.
release
