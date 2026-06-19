#!/usr/bin/env bash
#
# Stop hook — re-injects the standing implementation protocol on every stop so it
# survives every turn and context compression, and keeps the phase-by-phase loop
# going until the work is done.
#
# Safety:
#   - If this stop was itself triggered by a stop-hook continuation
#     (stop_hook_active == true), do NOT block again — prevents infinite loops.
#   - If the completion sentinel exists (.claude/.impl-complete), do NOT block —
#     lets the agent stop cleanly once the plan is fully implemented (or when
#     genuinely blocked and it needs to surface something).
#
# Otherwise it returns a Stop "block" decision whose reason is the protocol text,
# which the model receives as the instruction to continue.

set -euo pipefail

input="$(cat)"

# Resolve repo root from this script's location (.claude/hooks/ -> repo root).
root="$(cd "$(dirname "$0")/../.." && pwd)"
sentinel="$root/.claude/.impl-complete"

# Already continuing because of this hook, or work marked complete -> allow stop.
if printf '%s' "$input" | grep -q '"stop_hook_active"[[:space:]]*:[[:space:]]*true'; then
    exit 0
fi
if [ -f "$sentinel" ]; then
    exit 0
fi

PROTOCOL="$(cat <<'EOF'
STANDING IMPLEMENTATION PROTOCOL (re-injected on every stop):

Work the plan ONE phase at a time, in order. Stay on a SINGLE branch for the entire implementation — create it once at the start, never branch again, and open ONE pull request that you update per phase (do not open a new PR per phase). For each phase: (1) implement only that phase; (2) commit it on its own — one focused commit per phase, message stating which phase it completes, build green — and push so the PR updates; (3) immediately after committing, write/update a memory entry recording the branch name and PR number/URL, phases done (with commit hashes), what's in progress, the next phase, and any decisions/open threads — enough to fully resume after context compression. Update the memory after EVERY commit. DO NOT lint or judge your own codebase against the commandments (no commandments:judge, no self-sin-checking, no righteousness passes) — it is out of scope. Continue phase → commit → push → memory until the plan is fully implemented; don't pause between phases for confirmation unless genuinely blocked or a decision contradicts the plan.

(When the plan is fully implemented — or you are genuinely blocked and must surface it — create the file .claude/.impl-complete to release this loop, then stop.)
EOF
)"

jq -n --arg reason "$PROTOCOL" '{decision: "block", reason: $reason}'
