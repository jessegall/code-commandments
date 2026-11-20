---
name: commandments-shared-exceptions
description: "Writing a `throw`/`raise`, a `try`/`catch`/`except`, or an exception class in any language. Read this BEFORE an empty catch, a catch that returns null or rethrows what it caught, a new failure thrown inside a catch, or `throw new Exception(\"…\")`, and when an exceptions finding points here."
---

# Exceptions — fail loud, named, with the cause

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A failure is loud, named and carries its cause. Code that catches a failure does something about it,
> or does not catch it at all. These rules hold the same in every language, so every language is judged
> by them the same way.

## The principle

### Never swallow a failure

A `catch` whose body is empty, or only returns `null`, `false`, an empty string or an empty collection,
turns a failure into a value that looks like an answer. The caller cannot tell "nothing there" from
"something broke". Let the failure travel, or catch it at one named boundary that logs it and moves on.

### Catch only to do something

A `catch` whose whole body throws back the failure it caught does what writing no clause does. Delete
it, or give it work: roll back, release what was held, or throw a failure of your own with this one as
its cause.

### A wrapped failure keeps its cause

When a `catch` throws a new failure in place of the caught one, hand the caught one over as the cause
(`previous: $e`, `new X(msg, e)`, `{ cause: e }`, `raise X(...) from e`). Passing only its message
drops the trace that says where it started.

### Throw a named failure, and let it write its own message

`throw new Exception("…")` and `raise RuntimeError("…")` say nothing a caller can catch by name. Give
the failure a type of its own, and a named constructor that takes the values involved and writes the
sentence, so the message is written once, where the failure is defined, not at every throw.

## Rules

- [ ] Throw a named failure, never the language's catch-all one.
      _A failure type of its own, with a named constructor that takes the values involved and writes the message._
- [ ] Pass the values involved to a named constructor on the failure; never write the message at the throw.
      _A named constructor on the failure (`::for($order)`, `ForOrder(order)`) that writes the sentence once._
- [ ] Catch a failure only to do something about it: handle it, add what the caller needs, or let it pass by writing no clause.
      _Delete the clause and let the failure travel, or give it work: roll back, release what was held, or throw a failure of your own with it as the cause._
- [ ] Let a failure throw, or surface it named with its cause; never swallow a catch into an empty body or an absent value.
      _Rethrow it wrapped with the caught failure as its cause, or catch, log and skip at one named boundary._
- [ ] When wrapping a caught failure, hand the original over as its cause; never drop the trace.
      _Pass the caught failure as the cause (`previous: $e`, `new X(message, e)`, `{ cause: e }`, `raise X(...) from e`)._

## Worked example

### generic-exception — in PHP

A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one

```php
----------[ Bad ]----------

public function carrierName(Shipment $shipment): string
{
    return ($shipment->carrier ?? throw new \RuntimeException('shipment has no carrier'))->displayName();
}

----------[ Good ]----------

// in Shop\Shipping\TrackingFormatter
public function carrierNameNamed(Shipment $shipment): string
{
    return $shipment->carrier?->displayName()
        ?? throw CarrierMissing::for($shipment->id);
}

// Where the message went. The failure is named once, so every throw site says WHAT went wrong
// instead of spelling out a sentence the next site has to spell differently.

final class CarrierMissing extends RuntimeException
{
    public static function for(int|string $shipment): self
    {
        return new self("Shipment {$shipment} has no carrier.");
    }
}
```

### generic-exception — in TypeScript

A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one

```ts
----------[ Bad ]----------

export function zoneName(parcel: Parcel): string {
    if (parcel.zone > 4) {
        throw new Error('no such zone')
    } else {
        return `zone ${parcel.zone}`
    }
}

----------[ Good ]----------

export function postageInZone(zone: string, rates: Record<string, number>): number {
    if (!(zone in rates)) {
        throw new PostageUnknown(zone)
    }

    return rates[zone]
}
```

### generic-exception — in Vue

A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one

```vue
----------[ Bad ]----------

async (productId: number): Promise<number[]> => {
    const result = await fetch(`/api/products/${productId}/questions`)
    if (!result.ok) {
        throw new Error(result.statusText)
    }
    const questions: Entry[] = await result.json()
    const answered = questions.filter((question) => question.published)
    return answered.map((question) => question.id)
}
```

### generic-exception — in Python

A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one

```py
----------[ Bad ]----------

def basket(context):
    if context.session is None:
        raise RuntimeError("There is no active session in this context.")
    return context.session.basket

----------[ Good ]----------

# in sessions.py
class NoActiveSession(LookupError):
    @classmethod
    def reading(cls, what: str) -> "NoActiveSession":
        return cls(f"there is no active session to read the {what} from")

# in sessions.py
def current_basket(context):
    if context.session is None:
        raise NoActiveSession.reading("basket")
    return context.session.basket
```

### generic-exception — in C#

A throw of the language's catch-all failure (`Exception`, `RuntimeException`, `Error`, `RuntimeError`) instead of a named one

```cs
----------[ Bad ]----------

public string Account(string carrier)
{
    if (!accounts.TryGetValue(carrier, out var account))
    {
        throw new Exception($"No carrier is registered as '{carrier}'.");
    }

    return account;
}

----------[ Good ]----------

// in CarrierDirectory.cs
public string AccountOf(string carrier)
{
    if (!accounts.TryGetValue(carrier, out var account))
    {
        throw UnknownCarrier.Named(carrier);
    }

    return account;
}

// in CarrierDirectory.cs
public sealed class UnknownCarrier : InvalidOperationException
{
    private UnknownCarrier(string message) : base(message) {}

    public static UnknownCarrier Named(string carrier) => new($"No carrier is registered as '{carrier}'.");
}
```

The other 18 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=shared/exceptions` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `generic-exception`, `message-at-throw`, `rethrown-unchanged`, `swallow-catch`, `wrapping-without-cause`.
- `{{binary}} repent --sin=<sin>` — auto-fix, for `wrapping-without-cause`. Review it with `--dry-run` first.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 23 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`php/exceptions`](../../php/exceptions/SKILL.md) — what is particular to PHP's exceptions.
- [`python/exceptions`](../../python/exceptions/SKILL.md) — what is particular to Python's exceptions.
- [`csharp/exceptions`](../../csharp/exceptions/SKILL.md) — what is particular to C#'s exceptions.
