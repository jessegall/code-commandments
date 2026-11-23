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
