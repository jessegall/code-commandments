---
name: commandments-csharp-enums
description: "Modelling a value that can only be one of a handful in C# — a status, a kind, a mode held as a `string` and compared (`status == \"paid\"`), a set of `const string` fields standing in for cases, or a `switch` over string literals repeated in several classes. Read this BEFORE writing any of them."
---

# C# enums — a closed set is a type, with its knowledge on it

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A value that can only ever be one of a few is a **closed set**, and a closed set is a type.
> Held as a string, every comparison is a typo waiting to happen and every rule about the cases is
> written again wherever it is needed.

## The principle

### Seal the set

A value that can only ever be one of a handful — `"paid"`, `"pending"`, `"refunded"` — is an `enum`
(serialised by name with `JsonStringEnumConverter` where it must still read as its string on the
wire):

```csharp
public enum Status { Pending, Paid, Refunded }

public static class StatusRules
{
    public static bool IsSettled(this Status status) => status switch
    {
        Status.Pending => false,
        Status.Paid or Status.Refunded => true,
        _ => throw new ArgumentOutOfRangeException(nameof(status)),
    };
}
```

A typo is now a compile error where it is written, the compiler checks every `switch` covers every
case, and there is one place to read the whole set.

### Put the knowledge on the case

The per-case answers — a label, a colour, "is this final?" — live in one place beside the enum: an
extension method with one exhaustive `switch` expression, written with every case in view. A
comparison against string literals at a call site is that method, homeless: the next call site writes
it again, a little differently. When the cases differ in behaviour *and* data, the set is a sealed
class hierarchy, each case a type that answers for itself.

### Parse once, at the edge

A string arriving from JSON, a form or a database becomes the enum where it enters — the JSON
converter, or `Enum.Parse<Status>(raw)` — and fails there if it is not one of the cases. From then on
the code passes the enum, never the string.

## Rules

- [ ] Dispatch on the enum, never on strings that spell its members — parse the string into the enum where it enters.
      _Parse the string into the enum at the edge (`Enum.Parse<T>` or the JSON converter), switch on the enum, and put the per-case answer beside it._

## Worked example

### csharp-string-mirrors-enum

A `switch` or an `if` ladder dispatching on strings that are the names of an enum the codebase already declares — the enum, written out again as text

```cs
----------[ Bad ]----------

public void Handle(string reference, string status)
{
    switch (status)
    {
        case "shipped":
            notify($"{reference} is on its way");
            break;
        case "delivered":
            notify($"{reference} has arrived");
            break;
    }
}

----------[ Good ]----------

public void HandleParsed(string reference, string status)
{
    switch (Enum.Parse<OrderStatus>(status, ignoreCase: true))
    {
        case OrderStatus.Shipped:
            notify($"{reference} is on its way");
            break;
        case OrderStatus.Delivered:
            notify($"{reference} has arrived");
            break;
    }
}
```

## Commands

- `vendor/bin/commandments judge --skill=csharp/enums` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `csharp-string-mirrors-enum`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`backend/enums-with-behaviour`](../../backend/enums-with-behaviour/SKILL.md) — the same discipline on the PHP backend, with backed enums.
- [`python/enums`](../../python/enums/SKILL.md) — the same discipline in Python.
- [`csharp/flow`](../flow/SKILL.md) — the `else if` ladder over one subject that an enum and a `switch` expression replace.
