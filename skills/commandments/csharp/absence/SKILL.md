---
name: commandments-csharp-absence
description: "Modelling a value that might not be there in C# — a return typed `T?`, a `return null` for \"not found\", an `is null` check or a `?? default` at a call site, a `FirstOrDefault()`, a `TryGetValue`, the null-forgiving `!`, or deciding between throwing, returning an empty collection and returning `null`. Read this BEFORE writing any of them."
---

# C# absence — decide "missing" where the value is born

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> `null` is not a way to model absence. It is the *absence of a decision* about absence.
> Decide where the value is born — throw, hand back an empty form, or say honestly with `T?` that it
> may miss — and every caller downstream stops guessing.

## The principle

### Ask in order, stop at the first yes

1. **Can it actually be missing, or is "missing" a broken state?** A setting the program cannot run
   without, a record a caller just created, a key the code itself wrote — absence there is a
   failure. **Throw a named exception.** Do not return `null` for it.
2. **Does "nothing" have a natural empty form?** A search with no hits is an empty list; a lookup
   table with no entries is an empty dictionary; a behaviour with nothing to do is a Null Object —
   an instance whose members do nothing. Return that, and every caller loops or calls with no
   special case.
3. **Is it a genuine "look for it; it may miss" that more than one caller handles?** Then `T?`
   under nullable reference types is honest — the compiler makes every caller handle the missing
   case, and the check belongs at each caller *because* each one decides something different.
   `TryGetValue(key, out var value)` says the same for a lookup. If every caller writes the same
   `?? throw …` or `?? default`, the decision was the producer's: move it there.

### `?? default` answers the wrong question

`var name = user.Name ?? "";` fills a required slot with a value nobody chose, and loses the
question: was the name missing, or genuinely empty? If the value can be missing, handle that case;
if it cannot, the type should say so — make it non-nullable where it is born.

### `!` silences the compiler instead of deciding

The null-forgiving operator — `order.Customer!.Name` — tells the compiler "trust me" about a value
it has just told you may be null. Nothing checks the promise; the `NullReferenceException` simply
moves to wherever it turns out to be wrong. Either the value cannot be null — then give it a type
that says so — or it can, and the missing case needs handling, not hushing.

### The one honest `null`

A single local lookup checked right where it is produced — one caller, one `is null`, done — needs
no ceremony. The smell is a `null` that **travels**: returned, passed on, and re-checked at every
place it lands.

## Rules

<<<<<<< HEAD
- [ ] If a value can be missing, say so in its type with `string?`; don't default it to `""` and then check for the blank.
      _Make it `string? note = null` and check `note is null`, so nobody has to know that `""` means "not given"._
- [ ] Never fill a value with an invented `""`, `0` or `false` on absence — handle the missing case, or make the value certain where it is born.
=======
- [ ] Never fill a value with an invented `""`, `0` or `false` on absence — handle the missing case, or make the value certain at the point it is created.
>>>>>>> origin/main
      _Decide at the source: throw when the value must be there, or pass the absence on to a parameter typed to admit it (`T?`, `TryGetValue`). A real default (`?? "EUR"`) is a choice, not an invention._
- [ ] Never silence a nullable warning with `!` — decide the missing case where the value is created, or handle it here.
      _Make the value non-nullable at its source, throw a named exception where it must exist, handle the null branch, or narrow it honestly (`OfType<T>()`, a pattern, `TryGetValue`)._

## Worked example

### csharp-blank-string-default

a `string` parameter or property defaulted to `""` and then checked with `== ""` or `string.IsNullOrEmpty` — the blank is being used to mean "missing"

```cs
----------[ Bad ]----------

public static string Of(string heading, string strapline = "")
{
    if (strapline == "")
    {
        return heading;
    }

    return $"{heading} — {strapline}";
}

----------[ Good ]----------

public static string Lined(string heading, string? strapline = null)
{
    if (strapline is null)
    {
        return heading;
    }

    return $"{heading} — {strapline}";
}
```

The other 2 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=csharp/absence` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-blank-string-default`, `csharp-invented-default`, `csharp-null-forgiven`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 3 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/absence`](../../backend/absence/SKILL.md) — the same decision on the PHP backend, with `Option`.
- [`python/absence`](../../python/absence/SKILL.md) — the same decision in Python.
- [`csharp/exceptions`](../exceptions/SKILL.md) — when "missing" is a broken state, the named exception to throw.
