#!/bin/sh
# Stand a lane up. Run by `commandments lane open <name>` with:
#   $1  the lane's path      $2  the lane's name
#
# A worktree checks out TRACKED FILES ONLY — no vendor, no node_modules, no database, no .env.
# A lane missing them does not fail loudly: it runs its gates against nothing and reports green.
set -e

ROOT="$(git rev-parse --path-format=absolute --git-common-dir)/.."

# Copy vendor, never symlink it: composer resolves its base directory from its own real path,
# so a linked vendor silently loads and tests the MAIN checkout instead of this one.
# cp -c uses copy-on-write where the filesystem has it, which makes this close to free.
cp -c -R "$ROOT/vendor" ./vendor 2>/dev/null || cp -R "$ROOT/vendor" ./vendor

# npm install --silent
# cp "$ROOT/.env" .env

echo "lane $2 prepared at $1"