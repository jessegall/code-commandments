#!/usr/bin/env bash
#
# Bump jessegall/code-commandments to the latest version in each registered
# consumer project — nothing more. `composer require` does the whole job:
#
#   • it updates AND pins the constraint to ^latest automatically; and
#   • it fires the consumer's `post-update-cmd` — the `sync --after=previous`
#     that `install-sync-hook` wired in — which re-asserts everything a bump must
#     propagate: new prophets, scaffold, skills, the settings.json hook wiring,
#     and the CLAUDE.md section. (sync is idempotent; runs only when needed.)
#
# So there is no separate pin / sync / scaffold step here, and NO commit: the
# resulting changes are left in each consumer's working tree for you to review,
# commit, and push yourself (never pushed from here).
#
# Consumers are registered in this package's .env (NOT committed):
#
#   COMMANDMENTS_CONSUMERS=../smart-farmers-pos,../workflows
#
# Paths resolve relative to the package root (see .env.example). With no list
# configured, sibling repos that require the package on the current major are
# auto-detected (older-major pins and dev-branch scratch repos are skipped).
#
# Usage: scripts/update-consumers.sh        (or: composer update-consumers)
set -uo pipefail

PKG_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PACKAGE="jessegall/code-commandments"

# --- Auto-detect sibling consumers ------------------------------------------
# Fallback when no .env list is configured: scan sibling repos for a composer.json
# that requires the package, on the CURRENT major (older-major pins and dev-branch
# scratch repos are skipped, never silently bumped across a breaking boundary).
detect_consumers() {
  local latest_major out="" d constraint locked cmaj
  latest_major="$(composer show "$PACKAGE" --available 2>/dev/null | grep -oE 'versions : v?[0-9]+' | head -1 | grep -oE '[0-9]+')"
  for d in "$PKG_ROOT"/../*/; do
    d="${d%/}"
    [ -f "$d/composer.json" ] && [ -d "$d/.git" ] || continue
    [ "$(cd "$d" 2>/dev/null && pwd)" = "$PKG_ROOT" ] && continue
    constraint="$(php -r '$j=json_decode(file_get_contents($argv[1]),true)?:[]; foreach(["require","require-dev"] as $k){ if(isset($j[$k][$argv[2]])){ echo $j[$k][$argv[2]]; exit; } }' "$d/composer.json" "$PACKAGE" 2>/dev/null)"
    [ -z "$constraint" ] && continue
    locked="$(LOCK="$d/composer.lock" php -r '$f=getenv("LOCK"); if(!is_file($f))exit; $l=json_decode(file_get_contents($f),true)?:[]; foreach(array_merge($l["packages"]??[],$l["packages-dev"]??[]) as $p){ if($p["name"]==="'"$PACKAGE"'"){ echo $p["version"]; exit; } }' 2>/dev/null)"
    [[ "$locked" == dev-* ]] && continue
    cmaj="$(echo "$constraint" | grep -oE '[0-9]+' | head -1)"
    [ -n "$latest_major" ] && [ -n "$cmaj" ] && [ "$cmaj" != "$latest_major" ] && continue
    out="${out:+$out,}../$(basename "$d")"
  done
  echo "$out"
}

# --- Load consumer list: .env if configured, else auto-detect ----------------
ENV_FILE="$PKG_ROOT/.env"
CONSUMERS_RAW=""
if [ -f "$ENV_FILE" ]; then
  CONSUMERS_RAW="$(grep -E '^[[:space:]]*COMMANDMENTS_CONSUMERS[[:space:]]*=' "$ENV_FILE" \
    | tail -n1 | cut -d= -f2- | tr -d \'\" )"
fi

if [ -z "${CONSUMERS_RAW// /}" ]; then
  echo "• No COMMANDMENTS_CONSUMERS configured — auto-detecting sibling consumers…"
  CONSUMERS_RAW="$(detect_consumers)"
  if [ -z "${CONSUMERS_RAW// /}" ]; then
    echo "✗ No sibling repo requires $PACKAGE on the current major. Nothing to do."
    exit 1
  fi
  echo "  detected: $CONSUMERS_RAW"
fi

IFS=',' read -r -a CONSUMERS <<< "$CONSUMERS_RAW"

failures=0

for raw in "${CONSUMERS[@]}"; do
  rel="$(echo "$raw" | xargs)"   # trim surrounding whitespace
  [ -z "$rel" ] && continue

  case "$rel" in
    /*) dir="$rel" ;;
    *)  dir="$PKG_ROOT/$rel" ;;
  esac

  echo "════════════════════════════ $rel ════════════════════════════"

  if [ ! -d "$dir" ]; then
    echo "  ✗ Directory not found: $dir — skipping."
    failures=$((failures+1))
    continue
  fi

  dir="$(cd "$dir" && pwd)"

  # Pull the latest version (kept in require-dev). composer require pins ^latest
  # AND fires the consumer's post-update-cmd, which runs `sync --after=previous` —
  # that re-asserts prophets, scaffold, skills, hook wiring, and the CLAUDE.md
  # section. Nothing else to do here; the changes are left for you to review.
  echo "  → composer require --dev $PACKAGE (latest)"
  if ! composer --working-dir="$dir" require --dev "$PACKAGE" --no-interaction; then
    echo "  ✗ composer require failed in $dir."
    failures=$((failures+1))
  fi
done

echo
if [ "$failures" -gt 0 ]; then
  echo "Done with $failures failure(s)."
  exit 1
fi
echo "Done. Review, commit, and push each consumer yourself."
