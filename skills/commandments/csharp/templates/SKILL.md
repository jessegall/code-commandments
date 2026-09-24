---
name: commandments-csharp-templates
description: "Writing a multi-line string a C# program produces — generated code, a config block, a report, an email body. Read this the moment you find yourself joining a list of lines with `string.Join(\"\\n\", …)` or calling `AppendLine` line after line, so the OUTPUT stays readable in the source that produces it."
---

# C# templates — a multi-line string shows its output

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Can you see the output by reading the source? A raw string literal (`$""" … """`) can be read as the
> thing it produces; a list of line fragments joined with `"\n"` cannot.

## The principle

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

## Rules

- [ ] Write a multi-line text as a raw string literal that shows its output, not as lines joined with a newline.
      _Replace the join with `$"""` … `"""`, the lines written as they will appear and the values in `{placeholders}`._

## Worked example

### csharp-assembled-template

`string.Join("\n", new[] { "public class X", "{", "}" })` or `sb.AppendLine("…")` line after line — a multi-line text built from line fragments, so its shape cannot be seen in the source

```cs
----------[ Bad ]----------

public static string Body(string name, string tracking)
{
    var body = new StringBuilder();

    body.AppendLine($"Hi {name},");
    body.AppendLine();
    body.AppendLine("Your order is on its way.");
    body.AppendLine($"Track it with code {tracking}.");

    return body.ToString();
}

----------[ Good ]----------

public static string Written(string name, string tracking) => $"""
    Hi {name},

    Your order is on its way.
    Track it with code {tracking}.
    """;
```

## Commands

- `vendor/bin/commandments judge --skill=csharp/templates` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-assembled-template`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/templates`](../../backend/templates/SKILL.md) — the same discipline with PHP heredocs.
