---
name: commandments-python-templates
description: "Writing a multi-line string a Python program emits — generated code, a config block, a report, a message. Read this the moment you find yourself building a list of lines and `\"\\n\".join(...)`-ing it, so the OUTPUT stays readable in the source that produces it."
---

# Python templates — state the shape, don't assemble it

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Can you see the output by reading the source? A triple-quoted f-string can be read as the thing it
> produces; a list of line fragments joined with `"\n"` cannot.

## The principle

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

## Rules

- [ ] Write a multi-line string as one triple-quoted f-string (dedented) that shows its output, never a list of line fragments joined with a newline.
      _Replace the list and the join with `dedent(f"""…""")`, the varying parts as `{placeholders}` where they land._

## Worked example

### python-assembled-template

a multi-line string built as a list of line fragments and `"\n".join(...)`-ed, instead of a triple-quoted f-string that shows its output

```py
----------[ Bad ]----------

def slip(order_ref: str, carrier: str, weight_grams: int) -> str:
    return "\n".join([
        "PACKING SLIP",
        f"order:   {order_ref}",
        f"carrier: {carrier}",
        f"weight:  {weight_grams} g",
    ])

----------[ Good ]----------

def slip_shown(order_ref: str, carrier: str, weight_grams: int) -> str:
    return dedent(f"""\
        PACKING SLIP
        order:   {order_ref}
        carrier: {carrier}
        weight:  {weight_grams} g""")
```

## Commands

- `vendor/bin/commandments judge --skill=python/templates` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-assembled-template`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/templates`](../../backend/templates/SKILL.md) — the same discipline with PHP heredocs.
