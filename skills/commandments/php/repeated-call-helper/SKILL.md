---
name: commandments-php-repeated-call-helper
description: "When you keep calling the same `with`-style (variadic, named-argument) method the same way — `$element->copyWith(metadata: SomeData::from([...])->toArray())` at site after site — the repeated call plus its construction boilerplate is a missing method on the receiver's type. Read this when a `->with…(named: …)` / `->copyWith(named: …)` call, especially one wrapping a `Data::from([...])->toArray()`, recurs across call sites."
---

# Promote a repeated call to a method

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A call you write the same way over and over is a method you haven't named yet. When the same
> named argument, with the same construction boilerplate, rides a `with`-style API at site after site, give
> the receiver's type the operation: one `withMetadata($payload)` that hides the call AND the `from(…)->toArray()`
> wrap.

## The principle

A variadic, named-argument method — `copyWith(mixed ...$changes)`, a `with…()` builder — is a flexible,
low-level API: it maps whatever named arguments you pass onto the object. That flexibility is the point at
ONE call site. Across many, writing the *same* named argument the *same* way each time is duplication with
a tell: you've discovered a specific operation the type should name.

### The smell

The same shape recurs at site after site:

```php
$element->copyWith(metadata: PortInputPayload::from([...])->toArray());
```

Three things repeat every time: the `copyWith(metadata: …)` call, the `PortInputPayload::from([...])` build,
and the `->toArray()` flatten — your **two arrays** (the literal you write, then the array `toArray()`
returns). The low-level `with`-call is doing a specific job that has no name.

### The fix: name the operation on the type

Give the receiver's type the method the call sites keep spelling out:

```php
public function withMetadata(PortInputPayload $payload): static
{
    return $this->copyWith(metadata: $payload->toArray());
}
```

Now every site is `$element->withMetadata($payload)` — the `copyWith` mapping and the `from(…)->toArray()`
wrap live in ONE place, named for what they do. The generic `copyWith` stays for the genuinely one-off
change; the operation you kept repeating becomes first-class.

### The same query, written at every site

An Eloquent chain is an operation too. When `CommandRequest::query()->whereKey($id)->lockForUpdate()->sole()`
is written in three handlers, the lookup has no name and no owner: the day the lock or the scope changes,
one copy is missed. Name it once on the model, as a static finder or a scope
(`CommandRequest::lockedFor($id)`), and call that from each site. A chain written once or twice, or a chain
inside the model itself, is not this sin.

### When it is NOT this sin

- **A one-off.** A `with`-call written once is exactly what the flexible API is for — no method needed.
- **Genuinely different arguments.** If each site passes a *different* named argument (`copyWith(label: …)`
  here, `copyWith(icon: …)` there), there is no single operation to name.
- **Unrelated types.** Two different classes that both happen to have a `copyWith` are not one operation;
  only calls that resolve to the *same* method (the same class, or one it inherits) count as repetition.
### The same folder, spelled at every site

A folder the code reaches (`storage_path('exports/payouts/...')`, `__DIR__ . '/../stubs'`, `base_path('scripts/...')`)
is a place the code depends on, and it belongs to one owner: a method that returns the folder or a file in it, or a
constant. When three methods each spell the folder or a path inside it, moving or renaming the folder means finding
every spelling, and the one that is missed fails only at run time.

Give the folder one owner (`RefundFolder::file($name)`) and have every other site ask it. A path is grouped by the
root it starts from (`storage_path()`, the file's own `__DIR__`, a variable) plus the folders written below it, so a
nested folder counts with its parent.

What is not this sin: a configuration file declaring paths, which is itself the owner; the same relative path under
different roots (each package's own `__DIR__ . '/stubs'` is a different folder); and a file named next to the code
that names it.

## Rules

- [ ] A query several callers run is one named finder or scope on the model, written once.
      _Add a named static finder or scope to the model (`CommandRequest::lockedFor($id)`) and call it from each site._
- [ ] Promote a repeated `->with…(named: …)` call into a method on the receiver's type that hides the call and its construction boilerplate.
      _`$element->withMetadata($payload)` — a `withMetadata()` on the type doing `copyWith(metadata: $payload->toArray())`._

## Worked example

### repeated-query-chain

One Eloquent query chain written alike at three or more sites outside its model

```php
----------[ Bad ]----------

public function redeem(int $cardId, int $amountCents): int
{
    $card = GiftCard::query()->whereKey($cardId)->lockForUpdate()->sole();

    return min($amountCents, $card->balance_cents);
}

----------[ Good ]----------

// in Shop\Payments\GiftCardRedemption
public function coverable(int $cardId, int $amountCents): int
{
    return min($amountCents, GiftCard::lockedFor($cardId)->balance_cents);
}

// in Shop\Models\GiftCard
public static function lockedFor(int $cardId): self
{
    return self::query()->whereKey($cardId)->lockForUpdate()->sole();
}
```

The other 1 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=php/repeated-call-helper` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `repeated-query-chain`, `repeated-named-call`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 2 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
