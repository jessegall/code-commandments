#!/usr/bin/env bash
#
# Publish the v4 code-commandments skills + the CLAUDE.md skills block into a consumer.
#
#   ./scripts/publish-skills.sh [TARGET_DIR]
#
# - Skills: every skill under ./skills/ is copied into TARGET/.claude/skills/,
#   ALWAYS overwriting the old copy (stale files inside a skill are dropped).
#   Skills the consumer authored itself are left untouched.
# - CLAUDE.md: the managed block between the BEGIN/END markers is injected or
#   replaced in place. The rest of the document is never touched. A legacy
#   unmarked "## Skills — load before you work" section (from before markers
#   existed) is migrated to the marked block on first run.
#
set -euo pipefail
shopt -s nullglob

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SKILLS_SRC="$ROOT/skills"
TARGET="${1:-/path/to/app}"
SKILLS_DST="$TARGET/.claude/skills"
CLAUDE_MD="$TARGET/CLAUDE.md"

[ -d "$SKILLS_SRC" ] || { echo "error: no skills/ at $SKILLS_SRC" >&2; exit 1; }
[ -d "$TARGET" ]     || { echo "error: target not found: $TARGET" >&2; exit 1; }

mkdir -p "$SKILLS_DST"

echo "Publishing skills -> $SKILLS_DST"
count=0
for dir in "$SKILLS_SRC"/*/; do
  name="$(basename "$dir")"
  rm -rf "${SKILLS_DST:?}/$name"          # always overwrite
  cp -R "$dir" "$SKILLS_DST/$name"
  echo "  + $name"
  count=$((count + 1))
done
echo "  ($count skills)"

echo "Injecting CLAUDE.md skills block -> $CLAUDE_MD"
python3 - "$CLAUDE_MD" <<'PY'
import sys, re

path = sys.argv[1]

BEGIN = "<!-- BEGIN: code-commandments skills (auto-managed — do not edit between these markers) -->"
END   = "<!-- END: code-commandments skills -->"

BODY = """## Skills — load before you work

Code style in this package lives in the skills under `.claude/skills/`. Two tiers.

**MANDATORY LOAD — load these FIVE at the start of every coding session, before you explore-to-plan or
edit a single line** (via the Skill tool):

- **`fix-at-the-source`** — the root-cause-first move: trace a value to where it's born, never patch the
  symptom. Governs how every change is made.
- **`guard-clauses-and-flow`** — validate preconditions at the TOP (early return/throw), flat body, happy
  path last; never bury a check inline.
- **`value-objects`** — give related data a type: no loose `array<string,mixed>` bags, no data clumps, no
  primitive obsession. (Decide the type; then `spatie-data` is how to write it.)
- **`spatie-data`** — how to write and construct Spatie `Data` classes (this package is Data-class-heavy,
  so you will touch them).
- **`documentation`** — concise, present-tense docs; rare inline comments; never narrate the past.

Do not start work without all three loaded.

**KEEP IN MIND — load the moment the work touches them:**

- **`absence`** — modelling a value that might be missing (`?T`, `Option`, `null`, empty, Null Object, throw).
- **`exceptions`** — throwing or catching: named `::for()` factory exceptions, never swallow a failure.
- **`concurrent-state`** — state shared across requests/workers (`::for($id): Concurrent<self>`)."""

BLOCK = f"{BEGIN}\n{BODY}\n{END}"

try:
    text = open(path, encoding="utf-8").read()
    existed = True
except FileNotFoundError:
    text, existed = "", False

marked = re.compile(re.escape(BEGIN) + r".*?" + re.escape(END), re.S)

if marked.search(text):
    text = marked.sub(lambda _m: BLOCK, text)
    action = "replaced marked block"
else:
    # Migrate a legacy unmarked section, if present.
    legacy = re.compile(r"^## Skills — load before you work\b.*?(?=^## |\Z)", re.S | re.M)
    text, n = legacy.subn("", text)
    # Insert before the first "## " heading; else append.
    m = re.search(r"^## ", text, re.M)
    if m:
        i = m.start()
        text = text[:i].rstrip() + "\n\n" + BLOCK + "\n\n" + text[i:]
    else:
        text = (text.rstrip() + "\n\n" if text.strip() else "") + BLOCK + "\n"
    action = "migrated legacy section" if n else ("inserted block" if existed else "created file")

open(path, "w", encoding="utf-8").write(text)
print(f"  {action}")
PY

echo "Done."
