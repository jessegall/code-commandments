<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Backend\Spatie;

use JesseGall\CodeCommandments\Skills\Reference;
use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class SpatieDataHydration extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'backend/spatie-data-hydration',
            tier: Tier::Mandatory,
            order: 4,
        );
    }

    public function title(): string
    {
        return "Spatie Data — feed the framework, don't hand-build";
    }

    public function trigger(): string
    {
        return "How to CONSTRUCT and CONSUME a Spatie `Data` object at a call site without re-doing work the class already declares: never wrap a nested value in `X::from([...])` when the parent auto-hydrates the array; never `Enum::from(\$x)`/`new DateTime(\$x)` at a hydration site when the property auto-casts the scalar; never `array_map(E::for(...), \$xs)` to fill a `#[DataCollectionOf]` when a `#[WithCast]`/`IterableItemCast` owns the derivation; never build a `Data` only to `->toArray()` it, hand-roll a `toArray()`, compute a `#[Computed]` field at the call site, or hand-remap keys `#[MapInputName]` would map. Read this whenever you write or review a `::from([...])` array, a hydrator, or a call that fills a `Data` object.";
    }

    public function intro(): string
    {
        return "A `Data` object hydrates itself: `::from([...])` builds nested `Data`, `#[DataCollectionOf]`
collections, and enum/date casts straight from a plain array. Feed it the **simplest input** and let it
build. The moment you re-create a nested type, a cast, or a derivation at the call site, you've duplicated
the mapping the class owns — and coupled every caller to it.";
    }

    public function summary(): string
    {
        return "construct and consume `Data` objects without re-doing what the class declares — pass raw input, let `::from`/casts/collections build.";
    }

    public function principle(): string
    {
        return <<<'PRINCIPLE'
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
PRINCIPLE;
    }

    public function related(): array
    {
        return [
            SpatieData::class => "the sibling: how to AUTHOR the `Data` class this skill teaches you to feed — types, `::from` vs `new`, declaring casts/collections.",
        ];
    }

    public function references(): array
    {
        return [
            new Reference(
                name: 'mechanics',
                title: 'Spatie Data hydration mechanics',
                body: <<<'MD'
The exact APIs behind the rules — what to reach for when you apply a fix.

## What hydrates automatically (never build it by hand)

`::from([...])` runs recursively and builds these from a plain array with **no** call-site work:

| Property shape | Raw input it accepts |
|---|---|
| a nested `Data` (`public Sandbox $box`) | a nested array `['box' => ['label' => 'x']]` |
| `#[DataCollectionOf(E)] public array $items` | an array of arrays `['items' => [[...], [...]]]` |
| a backed **enum** (`public Status $status`) | the backing scalar `['status' => 'open']` |
| a `DateTimeInterface` (`public Carbon $at`) | a date string `['at' => '2024-01-01']` (built-in `DateTimeInterfaceCast`) |

So a nested `E::from([...])`, an `Enum::from($x)`, or a `new DateTime($x)` at a hydration site is redundant.

## Custom casts — for a DERIVATION the framework can't guess

A `Cast` turns a **simpler input** into a complex property value during `::from()`. Reach for one when the
raw value isn't the property's own array shape — e.g. an enum mapped to a rich `Data`, a value object built
from a scalar.

```php
final class StatusStyleCast implements \Spatie\LaravelData\Casts\Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        return ConsoleStatusStyle::for($value);   // $value is the raw enum; never null
    }
}
```

Attach it, and pass the raw list at the call site:

```php
#[WithCast(StatusStyleCast::class)]
public array $statuses;   // NB: a #[WithCast] property cannot be `readonly` — the framework injects after construction
```

- **Per-item casts for a collection:** implement `Cast` **and** `IterableItemCast`, and enable the feature
  in `config/data.php`: `'features' => ['cast_and_transform_iterables' => true]`. Then `castIterableItem`
  runs once per element — the home for `array_map(E::for(...), $xs)`.
- **`Castable` value object:** instead of a separate cast class, a value object can implement `Castable`
  with a static `castUsing(array $arguments): Cast`, so `#[WithCast]` isn't needed at each use.
- **Don't** write a cast for a nested `Data` (it auto-hydrates) or a plain scalar.

## `#[Computed]` — a field derived from other fields

Compute it once in the constructor, never at every call site:

```php
#[Computed]
public string $fullName;

public function __construct(public string $first, public string $last)
{
    $this->fullName = "{$this->first} {$this->last}";
}
```

You must **not** pass a computed value into `::from()` — a `CannotSetComputedValue` is thrown. It isn't
re-evaluated when its inputs change; build a new object to update it.

## Name mapping — one class-level mapper, not a hand-remap

For a snake_case boundary, one attribute maps every property — never a translation array at the call site:

```php
#[MapInputName(SnakeCaseMapper::class)]   // input only; #[MapName(...)] maps both directions
final class ContractData extends Data { /* camelCase properties */ }
```

Or set it app-wide in `config/data.php` under `name_mapping_strategy`.

## Serialization — `toArray()`, never a hand-rolled array

`toArray()` already flattens nested `Data`, collections, enums, and dates. Building `X::from([...])->toArray()`
round-trips for nothing, and a hand-written `['a' => $d->a, …]` drifts from the class the moment a field is
added. Call `$d->toArray()`.
MD,
            ),
        ];
    }
}
