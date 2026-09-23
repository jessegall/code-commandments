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
code nobody is reading. Git holds the history. When you replace code, replace it; don't annotate the grave.

### Never a strawman

`# not random`, `# no magic here` defend the code against a reading nobody made. State what it IS, or make it
self-evident and write nothing.

## Related skills

- [`backend/documentation`](../../backend/documentation/SKILL.md) — the same discipline for PHP docblocks.
- [`python/fix-at-the-source`](../fix-at-the-source/SKILL.md) — fix the shape instead of documenting the workaround.
