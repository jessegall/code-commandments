#!/usr/bin/env bash
#
# Surface only NEW open issues since the last check — the maintainer's
# issue-watch tool. Tracks which issue numbers have already been seen in a
# gitignored state file (.commandments/seen-issues), so a polling loop only ever
# shows you what you have not handled yet, with full body + comments and the
# response protocol baked in.
#
# Usage:
#   scripts/new-issues.sh            List NEW open issues (and mark them seen).
#   scripts/new-issues.sh --seed     Mark ALL current open issues as seen WITHOUT
#                                     printing them — establishes a baseline so a
#                                     loop starts from "now" instead of dumping the
#                                     whole backlog.
#
# Drive it on a 5-minute loop (the loop is a Claude /loop; this is the fetch):
#   /loop 5m composer issues:new
#
set -uo pipefail

REPO="${REPO:-jessegall/code-commandments}"
PKG_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STATE_DIR="$PKG_ROOT/.commandments"
STATE="$STATE_DIR/seen-issues"
mkdir -p "$STATE_DIR"
touch "$STATE"

open_numbers() {
  gh issue list --repo "$REPO" --state open --limit 200 \
    --json number,createdAt --jq 'sort_by(.createdAt) | reverse | .[].number' 2>/dev/null
}

mark_seen() { printf '%s\n' "$1" >> "$STATE"; }
is_seen()   { grep -qxF "$1" "$STATE"; }

# --seed: baseline — mark everything currently open as seen, print nothing new.
if [ "${1:-}" = "--seed" ]; then
  count=0
  while IFS= read -r n; do
    [ -z "$n" ] && continue
    is_seen "$n" || { mark_seen "$n"; count=$((count+1)); }
  done < <(open_numbers)
  echo "Baseline established — $count open issue(s) marked seen. Future runs show only NEW issues."
  exit 0
fi

new=()
while IFS= read -r n; do
  [ -z "$n" ] && continue
  is_seen "$n" || new+=( "$n" )
done < <(open_numbers)

if [ "${#new[@]}" -eq 0 ]; then
  echo "No new issues."
  exit 0
fi

echo "════════════ ${#new[@]} NEW issue(s) since last check ════════════"
echo
for n in "${new[@]}"; do
  echo "──────────────────────────── #$n ────────────────────────────"
  gh issue view "$n" --repo "$REPO" \
    --json number,title,state,author,createdAt,body,comments \
    --jq '"#\(.number)  \(.title)\nstate: \(.state)  •  @\(.author.login)  •  \(.createdAt)\n\n\(.body)\n\n── COMMENTS (\(.comments | length)) ──" + (.comments | map("\n\n@\(.author.login) (\(.createdAt)):\n\(.body)") | join(""))' \
    2>/dev/null
  echo
  mark_seen "$n"
done

cat <<'PROTOCOL'
════════════════════════════ ACT ON EACH ════════════════════════════
For every NEW issue above:
  • [prophet-report] (a wrong finding): reproduce it, then fix the prophet
    (prefer AST/semantic detection) + add a fixture, OR if it is correct,
    close with a reason. Release (patch/minor), then close the issue.
  • feature request / bug: implement it per CLAUDE.md conventions.
  • Commit with NO Co-Authored-By trailer; tag a new semver (patch=fix,
    minor=feature); push commit + tag; propagate to consumers.
Always read the COMMENTS — the decisive detail often lives there.
PROTOCOL
