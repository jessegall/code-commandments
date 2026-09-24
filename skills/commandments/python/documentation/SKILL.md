---
name: commandments-python-documentation
description: "How to document Python, and mostly not to. A docstring is a line or two about the code as it is NOW; a `#` comment is rare and only explains a non-obvious *why*; never narrate the past or a change (\"previously…\", \"used to…\", \"refactored to…\"). Read this the moment you are about to write a docstring, a `#` comment, or an `Args:`/`Returns:` section."
---

# Python documentation — concise, present-tense, rare

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A docstring describes the code as it is, in as few words as possible. A comment is a last resort. Neither
> is a changelog, a tutorial, or a story about the refactor.

## The principle

Every line a reader scans is a cost, and a line that restates the code, or tells how it came to be, is cost
with nothing back. Write documentation only where it tells the reader something the code does not.

### A docstring

One sentence saying what the module, class or function IS or DOES, present tense, about the code as it is
now. An `Args:`/`Returns:` block that only repeats the annotations (`name (str): the name`) says nothing the
signature does not; keep a section only for what a type cannot say: a unit, a constraint, which exception
and when. A docstring that runs to paragraphs usually means the thing it describes does too much.

### A `#` comment

Rare. The code already says what it does; a comment earns its place by saying *why*, when the reason cannot be
read off the code: a hidden invariant, a workaround for an outside bug, a constraint from elsewhere. A comment
that repeats the line below it (`# add the rate` above `total += rate`) is noise. Delete it.

### Never the past

`# formerly lived in checkout`, `# refactored to use the cache`, `# no longer a dict` describe a version of the
code nobody is reading. Git holds the history. When you replace code, just replace it — don't leave a comment explaining what it used to be.

### Never a strawman

`# not random`, `# no magic here` defend the code against a reading nobody made. State what it IS, or make it
self-evident and write nothing.

## Rules

- [ ] Say what the code is now; the history lives in git, not in a comment or a docstring.
      _Delete the history. If a reason still matters, state it in the present tense._
- [ ] Keep a class docstring to one tight paragraph; sections for attributes and examples are fine, an essay is not.
      _Cut the docstring to what the class is; if it takes an essay, split the class._
- [ ] A docstring must add meaning beyond the signature; drop entries that only repeat an annotation.
      _Delete the docstring, or write the sentence that says what the function does and describe only what a type cannot._
- [ ] A cross-reference must resolve: point it at the name that exists now, or delete it.
      _Repoint the reference at the current module or class, or remove it._
- [ ] State what the code is; a comment defending it against an objection nobody raised means the code should make itself plain.
      _Delete the defence. If the code needs it, make the code say what it is._
- [ ] A comment must say something the code does not; one whose every word is in the line below is noise.
      _Delete the comment, or replace it with the reason the code cannot state._

## Worked example

### python-archaeology-comment

a comment or docstring narrating the code's history — where it lived, what it replaced, what it no longer is

```py
----------[ Bad ]----------

# formerly lived in the checkout module, before invoices had their own
def next_number(last: int, prefix: str) -> str:
    return f"{prefix}-{last + 1:06d}"

----------[ Good ]----------

def next_invoice_number(last: int, prefix: str) -> str:
    # six digits, because the accounting export pads to a fixed width
    return f"{prefix}-{last + 1:06d}"
```

The other 5 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=python/documentation` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `python-archaeology-comment`, `python-bloated-docblock`, `python-ceremony-docblock`, `python-dangling-doc-reference`, `python-negative-space-comment`, `python-restated-comment`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 6 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/documentation`](../../backend/documentation/SKILL.md) — the same discipline for PHP docblocks.
- [`python/fix-at-the-source`](../fix-at-the-source/SKILL.md) — fix the shape instead of documenting the workaround.
