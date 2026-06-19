#!/usr/bin/env bash
#
# Pre-commit: keep the auto-generated README sections (prophet + command tables)
# in sync with the source classes. Regenerates them and, if README.md changed as
# a result, re-stages it so the commit always ships an up-to-date README.
#
# Installed by scripts/install-readme-hook.sh into .git/hooks/pre-commit.
set -euo pipefail

root="$(git rev-parse --show-toplevel)"

php "$root/scripts/generate-readme.php" >/dev/null

if ! git diff --quiet -- "$root/README.md"; then
    git add "$root/README.md"
    echo "↻ README.md regenerated (prophet/command tables) and re-staged."
fi
