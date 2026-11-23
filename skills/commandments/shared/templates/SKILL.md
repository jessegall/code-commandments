---
name: commandments-shared-templates
description: "Building a multi-line string in code: a generated file, a message, a query, a block of markup, put together from a list of lines joined by a newline or from concatenated fragments. Read this BEFORE you write the first fragment, and when a templates finding points here."
---

# Templates — state the shape, do not assemble it

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A block of fixed text in code should look, in the source, like the text it produces.
> Read this before you build a multi-line string (a message, a file, a query, a snippet of markup) out of pieces,
> and when a templates finding points here.
> The examples are pseudocode: read them as the language you are writing.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Write a fixed multi-line text as one multi-line literal, and interpolate only what varies.**

When a template is assembled from fragments, a list of lines joined by a newline, or a row of concatenations, the
reader has to run the code in their head to see the output. Indentation is hidden inside quotes, a missing line is
invisible, and a change to the layout touches every fragment.

Every language has a multi-line string. In it the text sits at its real indentation, line for line as it will be
written, and the parts that are computed stand out as the only interpolations. Compute those parts above the
literal, give them names, and drop them in.

## The sins

### assembled-template

A multi-line template built as a list of line fragments and joined with a newline. The output cannot be read in the
source that emits it. State it as one multi-line literal.

```pseudo
// Bad
function classFile(name, fields): string {
    lines = []
    lines.add("class " + name)   // ← the output cannot be seen in the source
    lines.add("{")
    for (field in fields) {
        lines.add("    public " + field.type + " " + field.name + ";")
    }
    lines.add("}")

    return lines.join("\n")
}

// Good
function classFile(name, fields): string {
    properties = fields.map(field => "    public " + field.type + " " + field.name + ";").join("\n")

    return """
        class {name}   // ← the text as it will be written
        {
        {properties}
        }
        """
}
```

## In your language

- **PHP.** A heredoc (`<<<PHP … PHP`) with `{$name}` interpolation; a nowdoc (`<<<'TXT'`) when nothing varies.
- **Python.** A triple-quoted f-string, with `textwrap.dedent` when it is indented in the source.
- **C#.** A raw string literal (`"""…"""`), interpolated with `$"""…"""`.
- **TypeScript.** A template literal with `${name}`.
- **Go.** A raw string in backticks, with `fmt.Sprintf` or `text/template` for what varies.

## Commands

- `{{binary}} judge --skill=shared/templates` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `assembled-template`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
