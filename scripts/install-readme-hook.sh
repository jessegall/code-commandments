#!/usr/bin/env bash
#
# Install the pre-commit hook that keeps the auto-generated README sections
# current (delegates to scripts/pre-commit-readme.sh). Idempotent — appends our
# block to an existing hook without clobbering it.
set -euo pipefail

root="$(git rev-parse --show-toplevel)"
hook="$root/.git/hooks/pre-commit"
begin="# >>> code-commandments readme-autogen >>>"
end="# <<< code-commandments readme-autogen <<<"
block="${begin}
\"\$(git rev-parse --show-toplevel)/scripts/pre-commit-readme.sh\"
${end}"

if [ -f "$hook" ] && grep -qF "$begin" "$hook"; then
    echo "README autogen hook already installed."
    exit 0
fi

if [ ! -f "$hook" ]; then
    printf '#!/usr/bin/env sh\n\n%s\n' "$block" > "$hook"
else
    printf '\n%s\n' "$block" >> "$hook"
fi

chmod +x "$hook"
echo "Installed the README autogen pre-commit hook."
