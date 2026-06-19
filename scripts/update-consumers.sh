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
# Paths are resolved relative to the package root. See .env.example.
#
# Usage: scripts/update-consumers.sh        (or: composer update-consumers)
set -uo pipefail

PKG_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PACKAGE="jessegall/code-commandments"
MARKER='@code-commandments-generated'

# --- Load consumer list from .env -------------------------------------------
ENV_FILE="$PKG_ROOT/.env"
if [ ! -f "$ENV_FILE" ]; then
  echo "✗ No .env found at $ENV_FILE"
  echo "  Create one (see .env.example):"
  echo "    COMMANDMENTS_CONSUMERS=../smart-farmers-pos,../workflows"
  exit 1
fi

# Read only the variable we care about; strip surrounding quotes/whitespace.
CONSUMERS_RAW="$(grep -E '^[[:space:]]*COMMANDMENTS_CONSUMERS[[:space:]]*=' "$ENV_FILE" \
  | tail -n1 | cut -d= -f2- | tr -d \'\" )"

if [ -z "${CONSUMERS_RAW// /}" ]; then
  echo "✗ COMMANDMENTS_CONSUMERS is empty or missing in $ENV_FILE"
  exit 1
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

  # 2. Register newly-shipped prophets into commandments.php. Idempotent;
  #    a consumer whose post-update-cmd already ran this just no-ops.
  echo "  → commandments sync --after=previous"
  if [ -x "$dir/vendor/bin/commandments" ]; then
    ( cd "$dir" && vendor/bin/commandments sync --after=previous ) || true
  elif [ -f "$dir/artisan" ]; then
    ( cd "$dir" && php artisan commandments:sync --after=previous ) || true
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
