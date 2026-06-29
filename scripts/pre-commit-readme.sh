#!/usr/bin/env bash
#
# Pre-commit: keep the auto-generated "Detectors" table in README.md in sync with
# Detectors\Catalog. Regenerates it and, if README.md changed, re-stages it so
# every commit ships an up-to-date README.
#
# Install with: scripts/install-readme-hook.sh
set -euo pipefail

root="$(git rev-parse --show-toplevel)"

php "$root/scripts/generate-readme.php" >/dev/null

if ! git diff --quiet -- "$root/README.md"; then
    git add "$root/README.md"
    echo "↻ README.md detectors table regenerated and re-staged."
fi
