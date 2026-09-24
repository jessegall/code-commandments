---
name: commandments-csharp-behaviour-per-method
description: "A C# parameter that picks which behaviour runs instead of feeding one. When a method's whole body is `if (flag) { … } else { … }`, it is two methods sharing a name, and every call reads `Render(order, true)`. Read this before adding a `bool` parameter, before making a required parameter nullable so that leaving it out means 'all of them', and when a call passes a bare `true` or `false`."
---

# C# behaviour per method — one method, one job

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> `Render(order, true)` — true *what*? The caller already knows which of the two things it wants; the
> flag is that decision, squeezed into a `bool` and handed over for the method to unpack again.

## The principle

A parameter is something a method **works with**. A flag argument is something it works *around*: it
arrives, is tested once, and decides which half of the body runs. The two halves were never one method —
they share a name and a signature, and nothing else.

- **`Send(order, true)` is unreadable.** Nothing at the call says what `true` means.
- **The halves cannot change separately.** A parameter one half needs becomes one the other must ignore.
- **It hides how the code is used.** Split, `SendDraft` has three callers and `SendFinal` thirty.
- **Flags multiply.** Two `bool`s are four behaviours in one body, and usually three were meant.

So **name the two behaviours**: `Render(order, true)` becomes `RenderCompact(order)` and
`RenderFull(order)`, and whatever they share becomes a private method both call.

### A named argument does not split it

`Render(order, compact: true)` is better than a bare `true` — but the body still runs one of two jobs behind
one name. A named argument is how to pass a flag that *tunes* a behaviour; a flag that *selects* one is still
two methods.

### The disguise: `null` that means "all of them"

A required parameter made nullable so that leaving it out asks a different question — `Fields(string kind)`
becomes `Fields(string? kind = null)`, and `Fields()` now means "every one". That is two questions behind one
name, chosen by what the call leaves out, which says even less than a bare `true`. The fix is the same:
`FieldsOfKind(kind)` and `Fields()`.

### What is NOT this sin

- **A flag that is data the method stores or passes on** — `SetVisible(visible)`: the value is the point.
- **A flag that tunes one behaviour** — `Parse(text, strict: true)`, where strictness changes what counts as
  an error inside one pass, not which pass runs.
- **A guard at the top** — `if (force) return Overwrite();` is a guard clause (see `csharp/flow`). The smell
  is a body that is *nothing but* the two-way branch.
- **An enum that names the choice** — `Render(order, Density.Compact)` already says what it means.

### The tell

The whole body is `if (flag) { … } else { … }`, a `flag ? A() : B()`, or `if (kind is null) … else …` on a
parameter that used to be required. Ask what you would call each half on its own. If both have an obvious
name, they are already two methods — give them their names.

## Rules

- [ ] Split a method a parameter chooses between into two methods, each named for what it does.
      _`Render(order, bool compact)` becomes `RenderCompact(order)` and `RenderFull(order)`, with anything they share in a private method both call._

## Worked example

### csharp-flag-argument

a method whose whole body branches on a `bool` parameter — `if (compact) … else …` — two methods sharing one name

```cs
----------[ Bad ]----------

public static string Line(string orderId, int cents, bool forCustomer)
{
    if (forCustomer)
    {
        return $"Order {orderId}: {cents / 100m:C}";
    }
    else
    {
        return $"{orderId};{cents}";
    }
}

----------[ Good ]----------

// in Summaries.cs
public static string CustomerLine(string orderId, int cents) => $"Order {orderId}: {cents / 100m:C}";

// in Summaries.cs
public static string LedgerLine(string orderId, int cents) => $"{orderId};{cents}";
```

## Commands

- `vendor/bin/commandments judge --skill=csharp/behaviour-per-method` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-flag-argument`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/behaviour-per-method`](../../backend/behaviour-per-method/SKILL.md) — the same discipline in PHP.
- [`csharp/enums`](../enums/SKILL.md) — when the choice has more than two cases, it is an `enum` — and the behaviour belongs beside it.
- [`csharp/flow`](../flow/SKILL.md) — an early return that guards is the shape this one is NOT.
