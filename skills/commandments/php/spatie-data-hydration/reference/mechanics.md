# Spatie Data hydration mechanics

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
