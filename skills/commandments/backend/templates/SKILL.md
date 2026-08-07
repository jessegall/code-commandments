---
name: commandments-backend-templates
description: "Writing a multi-line string a program emits — generated code, a config block, a report, a message. Read this the moment you find yourself building one line at a time and joining, so the OUTPUT stays readable in the source that produces it."
---

# Templates — state the shape, don't assemble it

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A template is a picture of its output. Write it as one — a heredoc, at its real
> indentation — and a reader sees what the program will emit. Assemble it from a list of line
> fragments and they have to run it in their head.

## The principle

The test is simple: **can you see the output by reading the source?**

A heredoc can be read as the thing it produces. Its indentation is real whitespace, its blank lines
are blank lines, and the parts that vary are interpolations sitting exactly where they will land. The
source and the output have the same shape, so a reader checks them against each other at a glance.

An array of line fragments joined with a newline has neither property:

- The **delimiters come apart.** `'/**'` and `' */'` are separate elements with the body between
  them, so a docblock is not visibly a docblock — it is three unrelated strings that happen to be
  adjacent.
- The **indentation is spelled**, not laid out. `'    $config->disable('` asks the reader to count
  characters inside a quote instead of seeing alignment.
- The **escapes fight the interpolation.** `"\${$var} = function (Config \$config): void {"` has to
  be decoded before you know what it emits.
- The **join is somewhere else.** `implode("\n", $lines)` can be ten lines away, so nothing at the
  array itself says these are LINES at all. Change the separator and every element silently means
  something different.

### The fix

State it once, as a heredoc, and interpolate what varies:

```php
$body = implode("\n", $entries);   // the part that is genuinely computed

return <<<PHP
    /**
     * {$purpose}
     */
    \${$var} = function (Config \$config): void {
        \$config->disable(
    {$body}
        );
    };
    PHP;
```

The fixed shape is now visible and the computed part is one hole in it.

### What is NOT this sin

- **Joining values the program computed.** `implode("\n", array_map(fn ($f) => $f->label, $findings))`
  is a list being presented, not a template — there is no fixed shape for a heredoc to state.
- **Joining into ONE line.** `implode(', ', $columns)` builds a value, not a layout.
- **A pair.** Two lines is not a template; its shape is already visible.
- **A genuinely dynamic set of lines**, where which lines appear is the whole point. Reach for a
  heredoc per line-kind if the lines themselves have shape; otherwise leave it.

### `sprintf` is the small sibling

For a ONE-line string with holes, `sprintf('%s (%d)', $name, $count)` states the shape the same way
concatenation does not. The principle is identical: put the literal in one piece and let the values
sit in it.

## Rules

- State a fixed multi-line string as a heredoc, at its real indentation, and interpolate what varies — never as a list of line fragments joined by a newline.
  _A heredoc (`<<<PHP` / `<<<'PHP'`), with the computed part as one interpolation._

## When it fires

- A multi-line template assembled as an array of line fragments and joined with a newline — the output is unreadable in the source that emits it

## Checklist

- [ ] State a fixed multi-line string as a heredoc, at its real indentation, and interpolate what varies — never as a list of line fragments joined by a newline.

## Related skills

- [`backend/documentation`](../documentation/SKILL.md) — the same instinct one scale down: say a thing once, in the form a reader can check.
- [`backend/value-objects`](../value-objects/SKILL.md) — a shape worth stating repeatedly is usually a type, not a string.
