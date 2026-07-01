---
name: commandments-backend-enums-with-behaviour
description: How a closed set of values is modelled — a native backed enum (never raw strings or a const class), with the knowledge keyed off its cases living ON the enum as methods, not re-inlined as a `match`/`switch` at every call site. Read this BEFORE you write a fixed set of string/int values, a `match`/`switch` over an enum (or over strings that mirror one), a `const` class of scalars, or a string field whose values are a closed set.
---

# Enums with behaviour — seal the set, put the logic on the type

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A closed set of values is a **type**, and what you *do* per value belongs **on** that type. The smell is
> a set expressed as loose strings, or an enum whose cases are matched over and over at the call sites
> instead of answering for themselves.

## The principle

Two moves, always together:

1. **Seal the set.** A fixed range of values — statuses, kinds, modes — is a **native backed enum**. Not
   raw string literals scattered across comparisons, not a `const` class of scalars, not a `string` field
   that "happens to" hold one of five values.
2. **Put the behaviour on the case.** The knowledge keyed off the set — a per-case value, a per-case
   decision — lives as a **method on the enum**, computed once with an exhaustive `match`. A `match` /
   `switch` over an enum *at a call site* is that method, homeless.

Sealing the set without moving the behaviour just relocates the `match` statements; the win is the enum
*answering for itself*.

### When to use this skill

Reach for this the moment you write:

- a **fixed set of string/int values** used as discrete choices (compared, `in_array`'d, switched on);
- a **`match` / `switch` over an enum** — especially the *same* enum in more than one place;
- a `match` / `switch` over **strings that mirror an enum's cases** (`'pending'`, `'done'` …);
- a **`const` class** of scalar values used as a closed set;
- a **`string`/`int` property** whose value space is actually closed.

## Rules

- Seal a closed set of values as a native backed enum, not a class of scalar `const`s or loose strings.
  _A native `enum X: string` with the values as cases._
- Put case-group membership on the enum (a method); don't hand-roll `$x === Enum::A || $x === Enum::B`.
  _A membership method on the enum (`$x->isFinal()`)._
- Put per-case behaviour on the enum; never `match`/`switch` over its `->value` at a call site.
  _A method on the backed enum (`$x->label()`, `$x->isPaid()`)._
- Test membership against the enum (its `cases()`/`tryFrom`), not an `in_array` of literals that mirror its values.
  _Use the enum (`Enum::tryFrom($x)` / a `cases()` check)._
- A `match`/`switch` `default` for an unhandled case must throw, not return `null`/`false`/`[]`.
  _`default => throw Unhandled::for($x)`._
- Dispatch over the enum's cases, not string/int literals that mirror its values.
  _Dispatch via a method on the backed enum's cases._

## Bad → good

```php
// Bad
final class PaymentStatuses
{
    /** Authorisation requested, awaiting the gateway. */
    const PENDING = 'pending';

    /** Funds held but not yet taken. */
    const AUTHORISED = 'authorised';

    /** Money moved; the order can ship. */
    const CAPTURED = 'captured';

    /** Reversed after capture. */
    const REFUNDED = 'refunded';
}

// Good
enum TaxBand: int
{
    case Standard = 2100;
    case Reduced = 900;
    case Zero = 0;
}
```

```php
// Bad
public function clearsImmediately(PaymentMethod $method): bool
{
    // not a coincidence — card and iDEAL both clear on the same rail
    if ($this->retries > 3) {
        return false;
    }

    return $method === PaymentMethod::Card || $method === PaymentMethod::Ideal;
}

// Good
public function clearsImmediatelyClean(PaymentMethod $method): bool
{
    if ($this->retries > 3) {
        return false;
    }

    return $method->isInstant();
}
```

```php
// Bad
public function colour(Product $product): string
{
    switch ($product->category->value) {
        case 'food':
            return 'green';
        case 'electronics':
            return 'blue';
        case 'clothing':
            return 'purple';
        default:
            return 'grey';
    }
}

// Good
public function colourViaEnum(Product $product): string
{
    return $product->category->badgeColour();
}
```

```php
// Bad
public function allowed(string $method): bool
{
    return in_array($method, ['card', 'ideal', 'paypal'], true);
}

// Good
public function allowedClean(string $method): bool
{
    return PaymentMethod::tryFrom($method) !== null;
}
```

```php
// Bad
public function for(Product $product): ?string
{
    return match ($product->priority) {
        1 => 'urgent',
        2 => 'normal',
        3 => 'low',
        default => null,
    };
}

// Good
public function strictFor(Product $product): string
{
    return match ($product->priority) {
        1 => 'urgent',
        2 => 'normal',
        3 => 'low',
        default => throw UnknownPriority::for($product->priority),
    };
}
```

```php
// Bad
public function endpoint(string $method): string
{
    return match ($method) {
        'card' => 'https://pay.test/card',
        'ideal' => 'https://pay.test/ideal',
        'paypal' => 'https://pay.test/paypal',
        default => 'https://pay.test/fallback',
    };
}

// Good
public function endpointClean(PaymentMethod $method): string
{
    return match ($method) {
        PaymentMethod::Card => 'https://pay.test/card',
        PaymentMethod::Ideal => 'https://pay.test/ideal',
        PaymentMethod::PayPal => 'https://pay.test/paypal',
    };
}
```

## When it fires

- Closed set as raw string literals / a `const` class of scalars (not a native enum) — `ConstClassEnumDetector`
- `$x === Enum::A || $x === Enum::B` — a hand-rolled case-group test — `EnumCaseOrChainDetector`
- `match`/`switch` over an enum's `->value` at a call site (homeless method) — `EnumValueMatchDetector`
- `in_array($x, [literals])` whose literals mirror an existing enum's cases — `InArrayMirrorsEnumDetector`
- `match` `default` that returns `null`/`''`/`[]` instead of throwing — `MatchDefaultReturnsNullDetector`
- `match` over string literals that mirror an existing enum's cases — `StringMatchMirrorsEnumDetector`

## Checklist

- [ ] Seal a closed set of values as a native backed enum, not a class of scalar `const`s or loose strings.
- [ ] Put case-group membership on the enum (a method); don't hand-roll `$x === Enum::A || $x === Enum::B`.
- [ ] Put per-case behaviour on the enum; never `match`/`switch` over its `->value` at a call site.
- [ ] Test membership against the enum (its `cases()`/`tryFrom`), not an `in_array` of literals that mirror its values.
- [ ] A `match`/`switch` `default` for an unhandled case must throw, not return `null`/`false`/`[]`.
- [ ] Dispatch over the enum's cases, not string/int literals that mirror its values.

## Related skills

- [`backend/value-objects`](../value-objects/SKILL.md) — an enum is the closed-set member of "give data a type"; reach for it when the type's values are a fixed set.
- [`backend/absence`](../absence/SKILL.md) — a missing/unhandled case is a throw, not a silent `default`.
- [`backend/exceptions`](../exceptions/SKILL.md) — a missing/unhandled case is a throw, not a silent `default`.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — seal the set where the value is born (a typed enum field) so downstream code never re-parses a string.
