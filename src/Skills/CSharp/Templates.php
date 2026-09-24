<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\CSharp;

use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class Templates extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'csharp/templates',
            tier: Tier::KeepInMind,
            order: 41,
        );
    }

    public function title(): string
    {
        return 'C# templates — a multi-line string shows its output';
    }

    public function trigger(): string
    {
        return "Writing a multi-line string a C# program produces — generated code, a config block, a report, an email body. Read this the moment you find yourself joining a list of lines with `string.Join(\"\\n\", …)` or calling `AppendLine` line after line, so the OUTPUT stays readable in the source that produces it.";
    }

    public function intro(): string
    {
        return "Can you see the output by reading the source? A raw string literal (`\$\"\"\" … \"\"\"`) can be read as the
thing it produces; a list of line fragments joined with `\"\\n\"` cannot.";
    }

    public function summary(): string
    {
        return 'a multi-line string is a raw string literal that SHOWS its output, never a list of line fragments joined.';
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
A raw string literal can be read as the thing it produces. Its indentation is real whitespace, its blank
lines are blank lines, and the parts that vary are `{placeholders}` sitting exactly where they will land. The
source and the output have the same shape, so a reader checks one against the other at a glance.

Line fragments joined with `"\n"`, or written one `AppendLine` at a time, have neither property:

- **The block comes apart.** `"public class Settings"` and `"{"` are separate strings, so a block is not
  visibly a block — it is strings that happen to be next to each other.
- **The indentation is typed out**, not laid out: the reader counts spaces inside quotes.
- **The join is somewhere else.** `string.Join("\n", lines)` can be ten lines below the list, so nothing at
  the list itself says these are lines.

### The fix

Write the fixed shape once, in a raw string literal, and fill in what varies. The closing `"""` sets the
indentation, so the template can sit at the code's own indentation:

```csharp
var body = string.Join("\n", settings.Select(s => $"    public string {s.Key} {{ get; }} = \"{s.Value}\";"));

return $"""
    public sealed class {name}Settings
    {
    {body}
    }
    """;
```

The fixed shape is visible, and the computed part is one hole in it.

### What is NOT this sin

- **Joining values the program computed.** `string.Join("\n", findings.Select(f => f.Label))` presents a
  list; there is no fixed shape for a template to state.
- **Joining into one line.** `string.Join(", ", columns)` builds a value, not a layout.
- **Two lines.** Two lines are not a template; their shape is already visible.

### An interpolated string is the small version

For a one-line string with holes, `$"{name} ({count})"` shows the shape where `name + " (" + count + ")"`
assembles it. The principle is the same: the literal in one piece, the values sitting in it.
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
        return [Language::CSharp];
    }
}
