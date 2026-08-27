---
name: commandments-backend-spatie-data-hydration
description: "How to CONSTRUCT and CONSUME a Spatie `Data` object at a call site without re-doing work the class already declares — the nested `X::from([...])` the parent would hydrate, the `Enum::from($x)`/`new DateTime($x)` a property already casts, the `array_map` filling a `#[DataCollectionOf]`, the hand-rolled `toArray()`, the `#[Computed]` field computed at the call site, the keys remapped by hand. Read this whenever you write or review a `::from([...])` array, a hydrator, or a call that fills a `Data` object."
---

# Spatie Data — feed the framework, don't hand-build

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A `Data` object hydrates itself: `::from([...])` builds nested `Data`, `#[DataCollectionOf]`
> collections, and enum/date casts straight from a plain array. Feed it the **simplest input** and let it
> build. The moment you re-create a nested type, a cast, or a derivation at the call site, you've duplicated
> the mapping the class owns — and coupled every caller to it.

## The principle

The sibling skill [`spatie-data`](../spatie-data/SKILL.md) teaches how to *author* a `Data` class. This one
teaches how to *feed* it. They share one root: the `Data` class is a declarative machine — `::from()`,
`::collect()`, casts, `#[DataCollectionOf]`, `#[Computed]`, and name mappers already do the array↔object
work. A **call site** that re-does any of it by hand is redundant, and it duplicates a mapping that should
live in exactly one place — the class.

### The one rule: pass the simplest input the class can build from

`::from([...])` runs the whole pipeline **recursively**. Every value in that array is fed to the matching
property through its own hydration — nested `Data`, typed collection, enum, or date. So the value you write
should be the **raw material**, not the finished object.

### Nested `Data` and collections auto-hydrate — don't wrap them

A property typed as a nested `Data` builds itself from a plain array; a `#[DataCollectionOf(E)]` builds each
element from an array. So `X::from([...])` sitting in a parent `::from` array is pure ceremony:

- `'sandbox' => ConsoleSandboxCopy::from(['label' => 'x'])` → just `'sandbox' => ['label' => 'x']`.
- `'modes' => [Mode::from([...]), Mode::from([...])]` → just `'modes' => [[...], [...]]`.

Pass the array; the parent nests it. (The one-argument, array-literal form is the redundant one — an
**object** source like `X::from($model)` is a real conversion, not this sin.)

### Enums and dates auto-cast — pass the scalar

Spatie casts enums (native) and `DateTimeInterface` (built-in) straight from their raw value. So constructing
them at a hydration site is redundant:

- `'status' => WorkflowRunStatus::from($raw)` → just `'status' => $raw`.

(A `tryFrom`, or a `new DateTime($x, $tz)` / `createFromFormat(...)` that carries a timezone or format the
default cast wouldn't reproduce, is **not** redundant — those change the semantics.)

### A derivation belongs in a cast, not a call-site `array_map`

When each element is **derived** from a simpler value through a factory — `array_map(E::for($enum), $cases)` —
auto-hydration can't help (the input isn't the element's array shape). But a **cast** can: a `#[WithCast]`
(or per-item `IterableItemCast`) on the collection property owns the `enum → E` derivation once, and every
caller just passes the raw list. A factory that closes over services/`$this` can't move into a per-item cast,
so it stays at the call site — that's the boundary of this rule.

### Don't build a `Data` only to discard it

Building `X::from([...])->toArray()` constructs a typed object just to flatten it back to an array — either
pass the source array, or type the receiving slot as `X` and pass the object. And to serialize a `Data`
object, call `->toArray()` — never hand-write `['a' => $d->a, 'b' => $d->b, …]`, which silently drifts from
the class the moment a field is added.

### Derive and map on the class, not the caller

A field that is a pure function of other fields is a `#[Computed]` property — computed once in the class, not
recomputed at every construction site. A boundary that renames keys (snake ↔ camel) is one class-level
`#[MapInputName(SnakeCaseMapper::class)]` — not a hand-written translation array at each `::from`.

## Rules

- [ ] Don't `->toArray()` a `Data` into a slot that re-hydrates it; pass the object (or the source array) directly.
      _Drop the `->toArray()` — the nested-`Data` / `#[DataCollectionOf]` slot takes the object as-is._
- [ ] Move an element derivation (`array_map(E::for(...), $xs)`) into a `#[WithCast]` / `IterableItemCast` on the collection property; pass the raw list.
      _`#[WithCast(SomeCast::class)] public array $items` — the cast runs `E::for(...)` per item; the call site passes the raw values._
- [ ] Map a snake_case boundary with one class-level `#[MapInputName(SnakeCaseMapper::class)]` + `::from($src)`, not a hand-written key translation.
      _`#[MapInputName(SnakeCaseMapper::class)]` on the class, then `SomeData::from($src)`._
- [ ] Pass the enum itself to an enum slot — Spatie's enum cast keeps it; don't destructure it to `->value` at the hydration site only for it to be re-hydrated.
      _`'status' => $order->status`, not `'status' => $order->status->value`._
- [ ] Pass the raw scalar to an enum / `DateTimeInterface` slot — Spatie auto-casts it; don't construct the value at the hydration site.
      _`'status' => $raw`, not `'status' => Status::from($raw)`._
- [ ] Pass the plain array for a nested `Data` / `#[DataCollectionOf]` slot — don't wrap it in `X::from([...])`.
      _`'slot' => ['a' => 1]` (or `[['a' => 1], ...]` for a collection), not `X::from(['a' => 1])`._

## Worked example

### data-to-array-roundtrip

A `X::from(...)->toArray()` sits in a `::from` slot typed `X` that re-hydrates it — build → array → build

```php
----------[ Bad ]----------

public function hold(BadgeCopy $badge, string $status): BadgeHolder
{
    $toned = new BadgeCopy($badge->label, $this->toneFor($status));

    return BadgeHolder::from(['badge' => $toned->toArray()]);
}

----------[ Good ]----------

// in Shop\Http\Pages\Hydration\BadgeHolderBuilder
// The FIX: the `badge` slot is typed `BadgeCopy`, so it takes the object as-is — no `->toArray()`,
// no rebuild.

public function holdReady(BadgeCopy $badge, string $status): BadgeHolder
{
    $toned = new BadgeCopy($badge->label, $this->toneFor($status));

    return BadgeHolder::from(['badge' => $toned]);
}

final class BadgeCopy extends Data
{
    public function __construct(public readonly string $label, public readonly string $tone) {}
}
```

The other 5 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=backend/spatie-data-hydration` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `data-to-array-roundtrip`, `derived-collection-cast`, `hand-key-remap`, `redundant-enum-unwrap`, `redundant-native-cast`, `redundant-nested-from`.
- `vendor/bin/commandments repent --sin=<sin>` — auto-fix, for `data-to-array-roundtrip`, `redundant-enum-unwrap`, `redundant-native-cast`, `redundant-nested-from`. Review it with `--dry-run` first.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 6 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.
- [Spatie Data hydration mechanics](reference/mechanics.md)

## Related skills

- [`backend/spatie-data`](../spatie-data/SKILL.md) — the sibling: how to AUTHOR the `Data` class this skill teaches you to feed — types, `::from` vs `new`, declaring casts/collections.
