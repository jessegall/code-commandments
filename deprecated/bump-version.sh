#!/bin/bash
# Usage: ./scripts/bump-version.sh [major|minor|patch]

set -e

VERSION_FILE="VERSION"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$ROOT_DIR"

if [[ ! -f "$VERSION_FILE" ]]; then
    echo "VERSION file not found"
    exit 1
fi

CURRENT_VERSION=$(cat "$VERSION_FILE" | tr -d '[:space:]')

# Parse version components
IFS='.' read -r MAJOR MINOR PATCH <<< "$CURRENT_VERSION"

case "${1:-patch}" in
    major)
        MAJOR=$((MAJOR + 1))
        MINOR=0
        PATCH=0
        ;;
    minor)
        MINOR=$((MINOR + 1))
        PATCH=0
        ;;
    patch)
        PATCH=$((PATCH + 1))
        ;;
    *)
        echo "Usage: $0 [major|minor|patch]"
        echo "  major: Bump major version (x.0.0)"
        echo "  minor: Bump minor version (0.x.0)"
        echo "  patch: Bump patch version (0.0.x)"
        exit 1
        ;;
esac

NEW_VERSION="${MAJOR}.${MINOR}.${PATCH}"

echo "$NEW_VERSION" > "$VERSION_FILE"

echo "Bumped version: $CURRENT_VERSION -> $NEW_VERSION"
