---
name: commandments-backend-spatie-data-hydration
description: How to CONSTRUCT and CONSUME a Spatie `Data` object at a call site without re-doing work the class already declares: never wrap a nested value in `X::from([...])` when the parent auto-hydrates the array; never `Enum::from($x)`/`new DateTime($x)` at a hydration site when the property auto-casts the scalar; never `array_map(E::for(...), $xs)` to fill a `#[DataCollectionOf]` when a `#[WithCast]`/`IterableItemCast` owns the derivation; never build a `Data` only to `->toArray()` it, hand-roll a `toArray()`, compute a `#[Computed]` field at the call site, or hand-remap keys `#[MapInputName]` would map. Read this whenever you write or review a `::from([...])` array, a hydrator, or a call that fills a `Data` object.
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

- Don't `->toArray()` a `Data` into a slot that re-hydrates it; pass the object (or the source array) directly.
  _Drop the `->toArray()` — the nested-`Data` / `#[DataCollectionOf]` slot takes the object as-is._
- Move an element derivation (`array_map(E::for(...), $xs)`) into a `#[WithCast]` / `IterableItemCast` on the collection property; pass the raw list.
  _`#[WithCast(SomeCast::class)] public array $items` — the cast runs `E::for(...)` per item; the call site passes the raw values._
- Map a snake_case boundary with one class-level `#[MapInputName(SnakeCaseMapper::class)]` + `::from($src)`, not a hand-written key translation.
  _`#[MapInputName(SnakeCaseMapper::class)]` on the class, then `SomeData::from($src)`._
- Pass the raw scalar to an enum / `DateTimeInterface` slot — Spatie auto-casts it; don't construct the value at the hydration site.
  _`'status' => $raw`, not `'status' => Status::from($raw)`._
- Pass the plain array for a nested `Data` / `#[DataCollectionOf]` slot — don't wrap it in `X::from([...])`.
  _`'slot' => ['a' => 1]` (or `[['a' => 1], ...]` for a collection), not `X::from(['a' => 1])`._

## Bad → good

```php
// Bad
public function make(?HeaderCopy $header): HeaderHolder
{
    return HeaderHolder::from(['header' => ($header ?? $this->default)->toArray()]);
}

// Good
public function fromData(HeaderCopy $header): MetaHolder
{
    return MetaHolder::from(['meta' => $header->toArray(), 'kind' => 'header']);
}
```

```php
// Bad
public function ofSize(int $size): TileGrid
{
    $size = min($size, self::MAX);

    if ($size < 1) {
        return TileGrid::from(['tiles' => []]);
    }

    return TileGrid::from(['tiles' => array_map(GridTile::make(...), range(0, $size - 1))]);
}

// Good
public function build(): ThemedLegend
{
    return ThemedLegend::from(['chips' => array_map(fn (ShipState $s) => StateChip::themed($s, $this->theme), ShipState::cases())]);
}
```

```php
// Bad
public function import(): ContractData
{
    $src = $this->rows->next();

    return ContractData::from([
        'recordCompany' => $src['record_company'],
        'signedAt' => $src['signed_at'],
    ]);
}

// Good
public function transformed(string $id): InvoiceData
{
    $row = $this->gateway->fetch($id);

    return InvoiceData::from([
        'invoiceNumber' => strtoupper($row['invoice_number']),
        'amountCents' => $row['amount_cents'],
    ]);
}
```

```php
// Bad
public function map(object $shipment): ShipmentTimes
{
    return ShipmentTimes::from([
        'shippedAt' => Carbon::parse($shipment->shipped_at),
        'carrier' => $shipment->carrier,
    ]);
}

// Good
public function build(string $code): TolerantState
{
    return TolerantState::from(['state' => FulfilmentState::tryFrom($code), 'raw' => $code]);
}
```

```php
// Bad
public function compose(): TabBar
{
    return TabBar::from(['tabs' => [
        TabCopy::from(['id' => 'edit', 'title' => 'Edit']),
        TabCopy::from(['id' => 'preview', 'title' => 'Preview']),
    ]]);
}

// Good
public function fromModel(object $model): ReadyBadgeStrip
{
    return ReadyBadgeStrip::from(['badge' => BadgeCopy::from($model)]);
}
```

## When it fires

- A `X::from(...)->toArray()` sits in a `::from` slot typed `X` that re-hydrates it — build → array → build — `DataToArrayRoundtripDetector`
- A `#[DataCollectionOf]` is filled by mapping a factory over inputs at the call site, where a `#[WithCast]` should own the derivation — `DerivedCollectionShouldCastDetector`
- A `::from([...])` mechanically renames `$src['snake_key']` → `camelKey` by hand, instead of a class-level `#[MapInputName]` — `HandKeyRemapDetector`
- An enum / date is constructed at a hydration site (`Enum::from($x)`, `new DateTime($x)`) where the property auto-casts the raw scalar — `RedundantNativeCastDetector`
- A nested `X::from([...])` fills a slot the parent `::from` already auto-hydrates from the array — `RedundantNestedFromDetector`

## Checklist

- [ ] Don't `->toArray()` a `Data` into a slot that re-hydrates it; pass the object (or the source array) directly.
- [ ] Move an element derivation (`array_map(E::for(...), $xs)`) into a `#[WithCast]` / `IterableItemCast` on the collection property; pass the raw list.
- [ ] Map a snake_case boundary with one class-level `#[MapInputName(SnakeCaseMapper::class)]` + `::from($src)`, not a hand-written key translation.
- [ ] Pass the raw scalar to an enum / `DateTimeInterface` slot — Spatie auto-casts it; don't construct the value at the hydration site.
- [ ] Pass the plain array for a nested `Data` / `#[DataCollectionOf]` slot — don't wrap it in `X::from([...])`.

## Reference

- [Spatie Data hydration mechanics](reference/mechanics.md)

## Related skills

- [`backend/spatie-data`](../spatie-data/SKILL.md) — the sibling: how to AUTHOR the `Data` class this skill teaches you to feed — types, `::from` vs `new`, declaring casts/collections.
