#!/usr/bin/env bash
#
# Bump jessegall/code-commandments to the latest version in each registered
# consumer project, re-sync prophets + scaffold, and commit ONLY the resulting
# composer.json, commandments.php (root or config/), and the generated scaffold
# files. The commit uses --no-verify so the consumer's own pre-commit gate does
# not block this maintenance commit.
#
# Consumers are registered in this package's .env (NOT committed):
#
#   COMMANDMENTS_CONSUMERS=../smart-farmers-pos,../workflows
#
# Paths are resolved relative to the package root. See .env.example. If no list is
# configured, the script AUTO-DETECTS sibling repos that require the package on the
# current major (older-major pins and dev-branch scratch repos are skipped).
#
# Safety guarantees (so a bump can never touch the developer's own work):
#   • stays on each consumer's current branch — never checks out / switches;
#   • commits ONLY its own paths via explicit pathspec (composer.json/lock when
#     tracked, commandments.php, .commandments-last-synced, generated scaffold) —
#     a bare commit could sweep the developer's pre-staged WIP, this never does;
#   • skips the commit entirely when none of those paths changed;
#   • never pushes — review and push yourself.
#
# Usage: scripts/update-consumers.sh        (or: composer update-consumers)
set -uo pipefail

PKG_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PACKAGE="jessegall/code-commandments"
MARKER='@code-commandments-generated'

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

  # Resolve relative to the package root.
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

  if [ ! -d "$dir/.git" ]; then
    echo "  ✗ Not a git repository: $dir — skipping."
    failures=$((failures+1))
    continue
  fi

  dir="$(cd "$dir" && pwd)"

  # 1. Pull the latest version. It lives in require-dev, so keep it there.
  #    --no-scripts: skip the consumer's own post-update hooks (e.g. artisan
  #    package:discover), which can fail for environment reasons unrelated to
  #    this bump (a missing redis, an unbootable app). We run the commandments
  #    sync ourselves below, so scaffolding still happens.
  echo "  → composer require --dev $PACKAGE (latest)"
  if ! composer --working-dir="$dir" require --dev "$PACKAGE" --no-scripts --no-interaction; then
    echo "  ✗ composer require failed — skipping commit for this consumer."
    failures=$((failures+1))
    continue
  fi

  # Composer sometimes writes a "*" constraint instead of a caret; pin it to
  # ^MAJOR.MINOR of the version it actually resolved to.
  ver="$(LOCK="$dir/composer.lock" python3 -c 'import json, os
d = json.load(open(os.environ["LOCK"]))
pk = [p for p in d.get("packages", []) + d.get("packages-dev", []) if p["name"] == "jessegall/code-commandments"]
print(pk[0]["version"].lstrip("v") if pk else "")' 2>/dev/null)"
  if [ -n "$ver" ]; then
    caret="^$(echo "$ver" | cut -d. -f1-2)"
    echo "  → pin constraint to $caret"
    composer --working-dir="$dir" require --dev "$PACKAGE:$caret" --no-update --no-scripts --no-interaction >/dev/null 2>&1
  fi

  # 2. Register newly-shipped prophets into commandments.php. ALWAYS runs, and a
  #    failure (or a missing runner) is surfaced LOUDLY — never swallowed with
  #    `|| true`. A silent skip here is exactly how "package updated but the new
  #    prophets were never registered" happens.
  echo "  → commandments sync --after=previous"
  if [ -x "$dir/vendor/bin/commandments" ]; then
    ( cd "$dir" && vendor/bin/commandments sync --after=previous ) \
      || echo "  ‼ WARNING: sync FAILED in $dir — new prophets may NOT be registered. Re-run: ( cd $dir && vendor/bin/commandments sync )"
  elif [ -f "$dir/artisan" ]; then
    ( cd "$dir" && php artisan commandments:sync --after=previous ) \
      || echo "  ‼ WARNING: sync FAILED in $dir — new prophets may NOT be registered. Re-run: ( cd $dir && php artisan commandments:sync )"
  else
    echo "  ‼ WARNING: no commandments runner (vendor/bin/commandments or artisan) in $dir — prophets were NOT synced!"
  fi

  # 2b. Refresh auto-managed scaffold files (Option, etc.) when their stubs
  #     changed — `sync` only CREATES missing files, it does not overwrite an
  #     existing scaffold class. `scaffold --auto` regenerates exactly the
  #     consumers that opted into scaffold.auto_refresh; a no-op for the rest.
  echo "  → commandments scaffold --auto"
  if [ -x "$dir/vendor/bin/commandments" ]; then
    ( cd "$dir" && vendor/bin/commandments scaffold --auto ) || true
  elif [ -f "$dir/artisan" ]; then
    ( cd "$dir" && php artisan commandments:scaffold --auto ) || true
  fi

  # 3. Collect ONLY the paths this bump owns: composer.json, the commandments
  #    config, and the generated scaffold files (identified by their marker).
  paths=( composer.json )
  # composer.lock + .commandments-last-synced — only when git-TRACKED. workflows
  # gitignores its lock (it's a library); some consumers ignore the synced marker.
  # Adding a gitignored path to the pathspec would make `git commit --` error.
  git -C "$dir" ls-files --error-unmatch composer.lock            >/dev/null 2>&1 && paths+=( composer.lock )
  git -C "$dir" ls-files --error-unmatch .commandments-last-synced >/dev/null 2>&1 && paths+=( .commandments-last-synced )
  [ -f "$dir/commandments.php" ]        && paths+=( commandments.php )
  [ -f "$dir/config/commandments.php" ] && paths+=( config/commandments.php )
  while IFS= read -r f; do
    [ -n "$f" ] && paths+=( "$f" )
  done < <(cd "$dir" && grep -rl --include='*.php' "$MARKER" . \
            --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git --exclude-dir=.claude 2>/dev/null)

  # Stage our paths (so new scaffold files are tracked), then bail if none of
  # them actually changed.
  git -C "$dir" add -- "${paths[@]}" 2>/dev/null

  if git -C "$dir" diff --cached --quiet -- "${paths[@]}"; then
    echo "  • Nothing changed — already up to date. No commit."
    continue
  fi

  # 4. Commit ONLY our paths via pathspec, so the commit never sweeps up other
  #    work the developer happens to have staged. --no-verify bypasses the
  #    consumer's pre-commit gate for this maintenance commit.
  echo "  → git commit --no-verify (scoped to bump paths)"
  if git -C "$dir" commit --no-verify \
       -m "chore: bump $PACKAGE to latest + re-sync prophets/scaffold" \
       -- "${paths[@]}"; then
    echo "  ✓ Committed."
  else
    echo "  ✗ Commit failed."
    failures=$((failures+1))
  fi
done

echo
if [ "$failures" -gt 0 ]; then
  echo "Done with $failures failure(s)."
  exit 1
fi
echo "Done. All consumers updated."
