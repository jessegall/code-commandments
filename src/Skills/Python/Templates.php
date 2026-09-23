<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Python;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Templates extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'python/templates',
            tier: Tier::KeepInMind,
            order: 40,
        );
    }

    public function title(): string
    {
        return "Python templates — state the shape, don't assemble it";
    }

    public function trigger(): string
    {
        return "Writing a multi-line string a Python program emits — generated code, a config block, a report, a message. Read this the moment you find yourself building a list of lines and `\"\\n\".join(...)`-ing it, so the OUTPUT stays readable in the source that produces it.";
    }

    public function intro(): string
    {
        return "Can you see the output by reading the source? A triple-quoted f-string can be read as the thing it
produces; a list of line fragments joined with `\"\\n\"` cannot.";
    }

    public function summary(): string
    {
        return 'a multi-line string is a triple-quoted f-string that SHOWS its output, never a list of line fragments joined.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A triple-quoted string can be read as the thing it produces. Its indentation is real whitespace, its blank
lines are blank lines, and the parts that vary are `{placeholders}` sitting exactly where they will land. The
source and the output have the same shape, so a reader checks one against the other at a glance.

A list of fragments joined with `"\n"` has neither property:

- **The delimiters come apart.** `"def main():"` and `"    return 0"` are separate elements, so a block
  is not visibly a block — it is strings that happen to be adjacent.
- **The indentation is spelled**, not laid out: the reader counts spaces inside quotes.
- **The join is somewhere else.** `"\n".join(lines)` can be ten lines below the list, so nothing at the
  list itself says these are LINES.

### The fix

State it once and fill in what varies — `textwrap.dedent` lets the template sit at the code's own
indentation:

```python
body = "\n".join(f"    {name} = {value!r}" for name, value in settings.items())  # the computed part

return dedent(f"""\
    class {name}Settings:
    {body}
""")
```

The fixed shape is visible, and the computed part is one hole in it.

### What is NOT this sin

- **Joining values the program computed.** `"\n".join(f.label for f in findings)` presents a list; there
  is no fixed shape for a template to state.
- **Joining into ONE line.** `", ".join(columns)` builds a value, not a layout.
- **A pair.** Two lines is not a template; its shape is already visible.

### An f-string is the small sibling

For a ONE-line string with holes, `f"{name} ({count})"` states the shape where `name + " (" + str(count) +
")"` assembles it. The principle is the same: the literal in one piece, the values sitting in it.
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            \JesseGall\CodeCommandments\Skills\Backend\Templates::class => 'the same discipline with PHP heredocs.',
        ];
    }

    public function languages(): array
    {
        return [Language::Python];
    }
}
