---
name: commandments-csharp-class-layout
description: "Adding a field, a constant or an auto-property to a C# class, record or struct — especially one that already has methods — or deciding where a new member goes. Read this BEFORE you declare state below a method, and when a class-layout finding points here."
---

# C# class layout — what the object holds, first

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> The top of a class is a list of what the object holds. The first screen should show its constants, its
> fields and its stored properties before any method asks for your attention. That only works if the list is
> complete: one field declared among the methods turns "the state is up here" into "the state is wherever
> you happen to find it".

## The principle

### The order is fixed

1. constants — `const` fields and `static readonly` values, facts about the type;
2. fields;
3. stored properties — auto-properties like `{ get; init; }`, and properties given a starting value;
4. constructors;
5. everything that does something — methods, and properties computed from the state above (`=> Net + Vat`),
   which are behaviour even though they read like data.

Within one group the order is yours: which constant comes first is the author's business, and a group of
related fields should stay together.

### The pull the other way

It is always the same, and always a mistake: a field added next to the method that uses it, because that is
where you were typing. It reads fine while the whole class is in your head. Afterwards it is a fact about
the object hidden among its behaviour, and the next reader, looking for what the class holds, cannot tell
when they have reached the end of the list.

If the top of the class feels too long to read, the class holds too much: split it. Don't fix a crowded
list by scattering it.

## Rules

- [ ] Declare constants, fields and stored properties above the constructor, before any method.
      _Move the declaration up to the other state at the top of the type._

## Worked example

### csharp-member-after-method

a field, constant or stored property declared below a constructor or a method — the type's state hidden among its behaviour

```cs
----------[ Bad ]----------

private const string Prefix = "INV";

----------[ Good ]----------

public sealed class CreditNoteNumbers(int year)
{
    private const string Prefix = "CN";

    private int issued;

    public string Next() => $"{Prefix}/{year}-{++issued:D5}";
}
```

## Commands

- `vendor/bin/commandments judge --skill=csharp/class-layout` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-member-after-method`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/class-layout`](../../backend/class-layout/SKILL.md) — the same discipline in PHP.
- [`csharp/value-objects`](../value-objects/SKILL.md) — a record whose members are its list of state — read at a glance.
