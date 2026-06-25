# Coalesce factories — hoist value-object construction ceremony

When a value object is built from a **nullable or shape-guarded array**, the
null-guard and the `@var` shape assertion get copy-pasted to every call site. A
total static `::coalesce()` factory owns that logic once, so callers read as a
single expression.

This applies to any **array-constructible** class: a Laravel `Fluent` bag, a
collection, or your own `__construct(array $x)`. (Spatie `Data` classes have
typed promoted params — they are *not* array-constructible, so this does not
apply to them; construct those via `::from()` — see the `immutable-data` skill.)

## Bad → good

Bad — inline null-guard + shape assertion at every call site:

```php
/** @var array<string, mixed> $snapshot */
$snapshot = T_Array::coalesce($run->context_snapshot);
$bag = new ValueBag($snapshot);

$bag = new ValueBag($value ?? []);
$bag = new ValueBag(is_array($value) ? $value : []);
$bag = ValueBag::make($value ?? []);          // ::make() is the same smell
```

Good — one total factory; the shape assertion lives there, once:

```php
final class ValueBag extends Fluent
{
    public static function coalesce(mixed $value): self
    {
        /** @var array<string, mixed> $attributes */
        $attributes = is_array($value) ? $value : T_Array::empty();

        return new self($attributes);   // the @var assertion belongs here
    }
}

$bag = ValueBag::coalesce($run->context_snapshot);
```

This also fixes a recurring PHPStan papercut: `new Fluent($jsonDecodedArray)`
fails max level because a decoded array is `array<array-key, mixed>`, not
`array<string, mixed>`. The `coalesce()` factory is the one home for that
`@var array<string, mixed>` assertion.

## Decision table

| Construction site | Verdict |
|---|---|
| `new Bag($v ?? [])`, `new Bag(is_array($v) ? $v : [])`, `new Bag(T_Array::coalesce($v))` | Add `Bag::coalesce()` and route call sites through it |
| `Bag::make($v ?? [])` | Same — `::make` is the named-constructor twin of `new` |
| `new Bag($alreadyTypedArray)` (no `??`, no shape guard) | Leave it — there is no ceremony to hoist |
| `new SomeService($v ?? [])` where the class is **not** array-constructible | Leave it — not this pattern |
| `new SpatieDataClass(...)` (typed promoted params) | Leave it — construct via `::from()` instead (see `immutable-data`) |

## When to reach for it

- An array-constructible value object is built from a **null-coalescing or
  shape-guarding** argument: `?? []`, `?? T_Array::EMPTY`, `T_Array::coalesce($v)`,
  or `is_array($v) ? $v : []` — especially when the same construction recurs.
- You keep repeating a `@var array<string, mixed>` assertion next to the `new`.

## When to leave it

- The argument is already a correctly-typed array — `new Bag($alreadyArray)`
  carries no null/shape ceremony.
- The class is **not** array-constructible (its constructor does not take an
  array as its first parameter) — it is a service or a typed Data class, not a
  bag. Adding the factory is a design call; this is advisory.

Enforced by **PreferCoalesceFactory**.
`commandments:scripture --prophet=PreferCoalesceFactory`.
