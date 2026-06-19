#!/usr/bin/env bash
#
# List every OPEN [prophet-report] issue with its full body AND all comments.
#
# Triage must ALWAYS read the comments — decisive details (exact sites, the
# accepted fix, "actually it's a false positive", scope narrowing) frequently
# live there, not in the body. `gh issue view N --comments` prints both, so use
# this (or `composer reports`) instead of a bare `gh issue view`.
#
# Usage: scripts/prophet-reports.sh [owner/repo]
set -euo pipefail

REPO="${1:-jessegall/code-commandments}"

numbers=$(gh issue list --repo "$REPO" --state open --json number,title \
  --jq '.[] | select(.title | startswith("[prophet-report]")) | .number')

if [ -z "$numbers" ]; then
  echo "No open prophet reports."
  exit 0
fi

for n in $numbers; do
  echo "════════════════════════════ #$n ════════════════════════════"
  gh issue view "$n" --repo "$REPO" --comments
  echo
done
