#!/usr/bin/env bash
#
# List every OPEN issue with its full body AND all comments — full context.
#
# Decisive details (exact sites, the accepted fix, "actually it's a false
# positive", scope narrowing) frequently live in the comments, not the body.
# `gh issue view N --comments` prints both, so use this (or `composer issues`)
# whenever you need to act on open issues rather than a bare `gh issue view`.
#
# Usage: scripts/open-issues.sh [owner/repo]
set -euo pipefail

REPO="${1:-jessegall/code-commandments}"

numbers=$(gh issue list --repo "$REPO" --state open --limit 200 \
  --json number,createdAt --jq 'sort_by(.createdAt) | reverse | .[].number')

if [ -z "$numbers" ]; then
  echo "No open issues."
  exit 0
fi

for n in $numbers; do
  echo "════════════════════════════ #$n ════════════════════════════"
  gh issue view "$n" --repo "$REPO" --comments
  echo
done
