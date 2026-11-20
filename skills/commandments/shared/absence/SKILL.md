---
name: commandments-shared-absence
description: "Writing a fallback for a value that might be missing in any language (`??`, `or`, `.get(k, default)`) and then comparing the result, or looping over `items ?? []` / `items or []`. Read this BEFORE writing either, and when an absence finding points here."
---

# Absence — ask whether it is there, directly and once

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A value that might be missing is asked about where it might be missing, plainly, and once. Folding
> "missing" into a stand-in value and then testing for that stand-in hides the question, and a
> collection that might be missing is ruled out by a guard before the work starts. These rules hold the
> same in every language, so every language is judged by them the same way.

## The principle

### Ask about absence directly

`(name ?? '') !== ''`, `(name or "") != ""`: the fallback turns "missing" into a blank, and the
comparison then asks whether the value is that same blank. Missing and empty end up in one branch
without anyone saying so. Ask the question you mean: whether the value is there (`name !== null`,
`name is not None`), and if empty counts too, say both halves out loud. Better still, make the value
always present where it is made, so only one question is left.

### A missing collection is a guard, not a loop header

`for item in items or []`, `foreach ($x[$k] ?? [] as …)`: the check for a collection that was never
handed over hides in the loop header. State it at the top with an early return, so the loop walks
something that is there, or make the caller always hand one over.

## Rules

- [ ] Require the text or give absence its own type.
      _Require the text or give absence its own type._
- [ ] Ask about absence directly; never fall back to a value only to compare against that same value.
      _Say both halves out loud (`name !== null && name !== ''`), or make the value always present where it is made so only one question is left._
- [ ] Decide which capabilities an object provides when giving it to its owner.
      _Give the owner a typed capability or an adapter that supplies the required behavior._
- [ ] Handle missing input before iteration or require a collection.
      _Handle missing input before iteration or require a collection._
- [ ] Build the collection from an explicit conditional contribution.
      _Build the collection from an explicit conditional contribution._
- [ ] Make the finder provide a value or report the miss.
      _Make the finder provide a value or report the miss._
- [ ] Keep absence explicit and resolve it before calling.
      _Keep absence explicit and resolve it before calling._
- [ ] Keep required state non-null, or pass it directly to the operation that uses it.
      _Remove the fallback by giving the value a certain type where it is set._
- [ ] Give a callback a usable default when every caller must be able to invoke it.
      _Use a named no-op or require the callback as a parameter._
- [ ] Resolve a required key or fail by name.
      _Make the lookup return a value or report the missing key._
- [ ] Make the field's declared type agree with the values it can hold.
      _Require the value where it is constructed, or handle its absence at the read._
- [ ] Supply the required value or make its absence part of the type.
      _Validate the data before constructing it, or make a separate type for the incomplete state._
- [ ] Validate decoded data at the boundary and return the type the caller can rely on.
      _Parse the decoded payload into a named result with checked fields._

## Worked example

### blank-string-default — in PHP

A blank string default stands for a missing required value

```php
----------[ Bad ]----------

public static function of(string $heading, string $strapline = ''): string
{
    if ($strapline === '') {
        return $heading;
    }

    return $heading . ' — ' . $strapline;
}

----------[ Good ]----------

public static function lined(string $heading, ?string $strapline = null): string
{
    if ($strapline === null) {
        return $heading;
    }

    return $heading . ' — ' . $strapline;
}
```

### blank-string-default — in TypeScript

A blank string default stands for a missing required value

```ts
----------[ Bad ]----------

export function labelInvoiceCode(name: string = ''): string {
    if (name === '') {
        return 'unassigned'
    }
    return name
}

----------[ Good ]----------

export function labelRequiredInvoiceCode(name: string): string {
    return name.toUpperCase()
}
```

### blank-string-default — in Python

A blank string default stands for a missing required value

```py
----------[ Bad ]----------

def book(self, parcel: str, tracking: str = "") -> str:
    if not tracking:
        tracking = self.api.issue(parcel)
    return self.api.book(parcel, tracking)

----------[ Good ]----------

def book_tracked(self, parcel: str, tracking: str | None = None) -> str:
    issued = tracking if tracking is not None else self.api.issue(parcel)
    return self.api.book(parcel, issued)
```

### blank-string-default — in C#

A blank string default stands for a missing required value

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

The other 49 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=shared/absence` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `blank-string-default`, `cancelled-coalesce`, `capability-probe-hub`, `coalesced-loop-subject`, `conditional-array-spread`, `de-nulled-finder`, `manufactured-fake-fill`, `masked-invariant`, `nullable-callback`, `nullable-registry-lookup`, `phantom-nullable`, `placeholder-filled-data`, `raw-decoded-array-return`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 53 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`php/absence`](../../php/absence/SKILL.md) — what is particular to PHP's absence.
- [`python/absence`](../../python/absence/SKILL.md) — what is particular to Python's absence.
- [`typescript/absence`](../../typescript/absence/SKILL.md) — what is particular to TypeScript's absence.
- [`csharp/absence`](../../csharp/absence/SKILL.md) — what is particular to C#'s absence.
