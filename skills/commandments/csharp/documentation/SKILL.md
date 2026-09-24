---
name: commandments-csharp-documentation
description: "How to document C#, and mostly not to. A `/// <summary>` is a line or two about the code as it is NOW; a `//` comment is rare and only explains a non-obvious *why*; never narrate the past or a change (\"previously…\", \"used to…\", \"refactored to…\"). Read this the moment you are about to write a `///` doc comment, a `<param>`/`<returns>` tag, or a `//` comment."
---

# C# documentation — short, present tense, rare

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A doc comment describes the code as it is, in as few words as possible. A comment is a last resort.
> Neither is a changelog, a tutorial, or a story about the refactor.

## The principle

Every line a reader scans costs them something, and a line that repeats the code, or tells how it came to
be, costs them and gives nothing back. Write documentation only where it tells the reader something the code
does not.

### A doc comment

One sentence in `<summary>` saying what the type or member IS or DOES, present tense, about the code as it is
now. A `<param name="order">The order.</param>` that only repeats the parameter's name and type says nothing
the signature does not; keep a tag only for what a type cannot say: a unit, a limit, which exception and when.
A summary that runs to paragraphs usually means the thing it describes does too much.

### A `//` comment

Rare. The code already says what it does; a comment earns its place by saying *why*, when the reason cannot be
read off the code: a hidden invariant, a workaround for a bug somewhere else, a constraint from outside. A
comment that repeats the line below it (`// add the rate` above `total += rate;`) is noise. Delete it.

### A `cref` points at something real

`<see cref="OrderService"/>` must name a type or member that exists. When the thing it named is renamed or
deleted, the reference is left pointing at nothing — point it at what replaced it, or remove it.

### Never the past

`// formerly lived in Checkout`, `// refactored to use the cache`, `// no longer a dictionary` describe a
version of the code nobody is reading. Git keeps the history. When you replace code, just replace it — don't
leave a comment explaining what it used to be.

### Never argue with a reader who isn't there

`// not random`, `// no magic here` defend the code against a reading nobody made. Say what it IS, or make it
obvious and write nothing.

## Rules

- [ ] Say what the code is now, never what it was; git keeps the history.
      _Delete the history. If something about the present needs saying, say that instead._
- [ ] Keep a type's doc comment to one short paragraph; if it needs more, the type is doing too much.
      _Cut the comment to one sentence about what the type is, and split the type if the rest describes a second job._
- [ ] A doc comment must say something the signature does not; drop tags that only repeat a name or a type.
      _Delete the comment, or write the sentence that says what the member does and describe only what a name and a type cannot._
- [ ] A `cref` must resolve: name what the code is called now, spelled so it reaches it from here, or delete it.
      _Point the `cref` at what the name became, qualified or imported so it resolves here (the compiler warns CS1574 until it does); if nothing replaced it, drop the reference._

## Worked example

### csharp-archaeology-comment

a comment that tells the code's past — `// formerly lived in CheckoutService`, `// refactored to use the cache` — describing a version nobody is reading

```cs
----------[ Bad ]----------

public static int Days(bool member) => member ? 60 : 30;

----------[ Good ]----------

public static int DaysFor(bool member) => member ? 60 : 30;
```

The other 3 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=csharp/documentation` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-archaeology-comment`, `csharp-bloated-docblock`, `csharp-ceremony-docblock`, `csharp-dangling-doc-reference`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 4 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/documentation`](../../backend/documentation/SKILL.md) — the same discipline for PHP docblocks.
- [`csharp/fix-at-the-source`](../fix-at-the-source/SKILL.md) — fix the shape instead of documenting the workaround.
