---
name: commandments-backend-guard-clauses-and-flow
description: How a method body is shaped — validate preconditions at the TOP with early return/throw, keep the body flat (no if/elseif/else ladders, no deep nesting), and run the happy path last. NEVER bury a check inline (`($x ?? throw …)->y()`) or in a nested branch. Read this BEFORE writing a method body, a precondition/null check, an `if`, or anything that throws or returns early.
---

# Guard clauses & flow — check at the top, then go straight

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Decide the unhappy paths first, at the door, and leave. What's left is the happy path, flat and
> unindented. A method should read top-to-bottom: *here's what would stop us → here's the work.*

## The principle

Every precondition a method depends on — a value that must be present, a state that must hold — is checked
**at the top** and short-circuits with a `return` or a `throw`. By the time control reaches the real work,
everything it needs is guaranteed, so the work runs at the base indentation level with no `else` and no
nesting. The shape itself documents the contract.

The opposite — burying a check inside an expression, or wrapping the happy path in `if (ok) { … }` — hides
the contract and pushes the body rightward until it's unreadable.

### When to use this skill

Reach for this the moment you are about to write:

- a **precondition / null / state check** at the start of a method;
- an `if` that decides whether the rest of the method runs;
- an `if/elseif/else` chain, or a branch nested two-deep;
- anything that **throws or returns early**.

## Rules

- Flatten with guard clauses — never nest `if`s three deep into a pyramid.
- Replace a 4+ branch if/elseif ladder with a `match`, a method on the type, or polymorphic dispatch.
  _A `match`, a method on the type, or polymorphic dispatch._
- Guard at the top with an early `throw`; don't bury a `?? throw` mid-expression feeding further work.
- Use a `continue` guard so the loop body stays flat; don't wrap the whole body in an `if`.
- Unfold a nested/chained ternary into a `match` or guards; don't hide branching in `$a ? $b : ($c ? $d : $e)`.
  _A `match`, or early-return guards._
- Drop the `else` after an `if` branch that already returns/throws/continues/breaks.

## Bad → good

```php
// Bad
public function resolve(Product $product, array $overrides, string $region): int
{
    if (array_key_exists($region, $overrides)) {
        if ($product->price_cents > 0) {
            if ($overrides[$region] < $product->price_cents) {
                return $overrides[$region];
            }
        }
    }

    return $product->price_cents;
}

// Good
public function resolveFlat(Product $product, array $overrides, string $region): int
{
    if (! array_key_exists($region, $overrides)) {
        return $product->price_cents;
    }

    if ($product->price_cents <= 0) {
        return $product->price_cents;
    }

    return min($overrides[$region], $product->price_cents);
}
```

```php
// Bad
public function band(int $grams): string
{
    if ($grams < 250) {
        return 'letter';
    } elseif ($grams < 2_000) {
        return 'parcel-s';
    } elseif ($grams < 10_000) {
        return 'parcel-m';
    } else {
        return 'parcel-l';
    }
}

// Good
public function bandByMatch(int $grams): string
{
    return match (true) {
        $grams < 250 => 'letter',
        $grams < 2_000 => 'parcel-s',
        $grams < 10_000 => 'parcel-m',
        default => 'parcel-l',
    };
}
```

```php
// Bad
public function carrierName(Shipment $shipment): string
{
    return ($shipment->carrier ?? throw new \RuntimeException('shipment has no carrier'))->displayName();
}

// Good
public function carrierNameGuarded(Shipment $shipment): string
{
    if ($shipment->carrier === null) {
        throw CarrierMissing::for($shipment->id);
    }

    return $shipment->carrier->displayName();
}
```

```php
// Bad
public function process(array $rows): void
{
    foreach ($rows as $row) {
        if ($row->total > 0) {
            $this->normalise($row);
            $this->persist($row);
        }
    }
}

// Good
public function process(array $rows): void
{
    foreach ($rows as $row) {
        if ($row->total <= 0) {
            continue;
        }

        $this->normalise($row);
        $this->persist($row);
    }
}
```

```php
// Bad
private function band(int $score): string
{
    return $score >= 90 ? 'A' : ($score >= 75 ? 'B' : 'C');
}

// Good
private function bandMatched(int $score): string
{
    return match (true) {
        $score >= 90 => 'A',
        $score >= 75 => 'B',
        default => 'C',
    };
}
```

```php
// Bad
public function inStock(array $products): array
{
    $available = [];

    foreach ($products as $product) {
        if ($product->stock <= 0) {
            continue;
        } else {
            $available[] = $product;
        }
    }

    return $available;
}

// Good
public function available(array $products): array
{
    $available = [];

    foreach ($products as $product) {
        if ($product->stock <= 0) {
            continue;
        }

        $available[] = $product;
    }

    return $available;
}
```

## When it fires

- `if` nested 3-deep (a pyramid — hoist guards / extract) — `DeepNestingDetector`
- if/elseif ladder of 4+ branches (should be match/dispatch) — `IfElseLadderDetector`
- `?? throw` / `=== null ? …` feeding further work on the same line (inline throw mid-expression) — `InlineThrowDetector`
- Loop body (multi-statement) wrapped in an `if` instead of `continue` guard — `LoopInvertedGuardDetector`
- Nested/chained ternary `$a ? $b : ($c ? $d : $e)` (hidden control flow) — `NestedTernaryDetector`
- `else` after an `if` branch that already returns/throws (redundant) — `RedundantElseDetector`

## Checklist

- [ ] Flatten with guard clauses — never nest `if`s three deep into a pyramid.
- [ ] Replace a 4+ branch if/elseif ladder with a `match`, a method on the type, or polymorphic dispatch.
- [ ] Guard at the top with an early `throw`; don't bury a `?? throw` mid-expression feeding further work.
- [ ] Use a `continue` guard so the loop body stays flat; don't wrap the whole body in an `if`.
- [ ] Unfold a nested/chained ternary into a `match` or guards; don't hide branching in `$a ? $b : ($c ? $d : $e)`.
- [ ] Drop the `else` after an `if` branch that already returns/throws/continues/breaks.

## Related skills

- [`backend/exceptions`](../exceptions/SKILL.md) — *how* a guard throws (named factory, never a message string).
- [`backend/absence`](../absence/SKILL.md) — *whether* a missing value is a guard-and-throw at all, vs Option / empty / default.
- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — if every caller re-guards the same value, the guard belongs upstream where the value is born.
