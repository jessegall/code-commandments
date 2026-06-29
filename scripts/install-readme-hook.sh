#!/usr/bin/env bash
#
# Installs the README pre-commit hook (scripts/pre-commit-readme.sh) into
# .git/hooks/pre-commit, so the auto-generated detectors table is regenerated and
# re-staged on every commit. Idempotent. Run once after cloning.
set -euo pipefail

root="$(git rev-parse --show-toplevel)"
hook="$root/.git/hooks/pre-commit"

cat > "$hook" <<'SH'
#!/usr/bin/env bash
set -euo pipefail
"$(git rev-parse --show-toplevel)/scripts/pre-commit-readme.sh"
SH

chmod +x "$hook"
echo "✓ Installed README pre-commit hook at .git/hooks/pre-commit"
