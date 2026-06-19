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
  echo "  → composer require --dev $PACKAGE (latest)"
  if ! composer --working-dir="$dir" require --dev "$PACKAGE" --no-interaction; then
    echo "  ✗ composer require failed — skipping commit for this consumer."
    failures=$((failures+1))
    continue
  fi

  # 2. Register newly-shipped prophets + (auto-)refresh scaffold. Idempotent;
  #    a consumer whose post-update-cmd already ran this just no-ops.
  echo "  → commandments sync --after=previous"
  if [ -x "$dir/vendor/bin/commandments" ]; then
    ( cd "$dir" && vendor/bin/commandments sync --after=previous ) || true
  elif [ -f "$dir/artisan" ]; then
    ( cd "$dir" && php artisan commandments:sync --after=previous ) || true
  fi

  # 3. Stage ONLY composer.json, the commandments config, and scaffold files.
  git -C "$dir" add -- composer.json 2>/dev/null
  [ -f "$dir/commandments.php" ]        && git -C "$dir" add -- commandments.php
  [ -f "$dir/config/commandments.php" ] && git -C "$dir" add -- config/commandments.php

  # Generated scaffold files carry the marker; stage every one (git ignores
  # the unchanged ones, so only real updates land in the commit).
  while IFS= read -r f; do
    [ -n "$f" ] && git -C "$dir" add -- "$f"
  done < <(cd "$dir" && grep -rl --include='*.php' "$MARKER" . \
            --exclude-dir=vendor --exclude-dir=node_modules --exclude-dir=.git 2>/dev/null)

  # 4. Commit (only if something staged), bypassing the pre-commit gate.
  if git -C "$dir" diff --cached --quiet; then
    echo "  • Nothing changed — already up to date. No commit."
    continue
  fi

  echo "  → git commit --no-verify"
  if git -C "$dir" commit --no-verify -m "chore: bump $PACKAGE to latest + re-sync prophets/scaffold"; then
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
