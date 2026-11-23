---
name: commandments-php-spatie-data
description: "How to write and construct a Spatie Data class — final, public readonly promoted props, honest field types, `::from([...])` rather than `new`, `from<Type>` magic factories, `#[DataCollectionOf]` collections, `Optional` vs `?T` vs a default, mapping, casts and validation. Read this FIRST whenever you write or review a `Data` class, a `::from`, a `new SomeData`, a hydrator that fills fields one by one, an `@method from`/`collect` docblock hint, or a `#[...]` attribute on a Data property."
---

# Spatie Data — let the class build itself

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A Spatie `Data` class is a typed, sealed description of one piece of data: what is always there, what may be left
> out, and how it is built. Write it so the type tells the truth and the package does the work.
> Read this before you write or review a `Data` class, a `::from`, a `new SomeData`, a loop that fills Data objects
> one by one, or an attribute on a Data property, and when a spatie-data finding points here.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Seal the class, type each field as what it really is, and build it through `::from`.**

1. **A Data class is a leaf.** `final`, with `public readonly` promoted constructor properties.
2. **Each field has one honest type.** There are three different things a field can be, and each has its own
   spelling:

   | The field is | Type it |
   |---|---|
   | always present | `T`, no default |
   | sometimes left out (the key is missing on the wire) | `T\|Optional = new Optional()` |
   | present, and "none" is a real value | `?T`, or better a Null Object or an identity default |

   A class where every field is nullable, or every field is `Optional`, promises nothing. When a whole sub-object
   may be absent, put the `Optional` on the field that holds it and keep its own fields concrete.
3. **`::from` builds it.** Never `new`: `::from` runs the casts, the mapping and the validation. A collection is
   typed `array` with `#[DataCollectionOf]` and built with `::collect`. A value object is built by a cast that is
   written once, not by hand at every caller.
4. **What goes to the frontend is generated.** A `#[TypeScript]` class needs every nested Data class to be
   `#[TypeScript]` too, or the generated type has a hole.

```php
// Bad
class OrderData extends Data                                   // ← not final
{
    public function __construct(
        public ?string $number = null,                         // ← an order always has a number
        public ?CustomerData $customer = null,
        public ?DataCollection $lines = null,                  // ← untyped items
    ) {}
}

$order = new OrderData($row['number'], new CustomerData(...), null);   // ← skips casts and validation

// Good
final class OrderData extends Data
{
    public function __construct(
        public readonly string $number,
        public readonly CustomerData $customer,
        #[DataCollectionOf(OrderLineData::class)]
        public readonly array $lines,
        public readonly CouponData|Optional $coupon = new Optional(),
    ) {}
}

$order = OrderData::from($row);
```

## The sins

### non-final-data

A Data class that is not `final`, or whose properties are not `readonly` promoted. It is a leaf, not a base class,
and a value that can be changed after it was built cannot be trusted.

```php
// Bad
class AddressData extends Data                     // ← can be extended
{
    public string $street;                         // ← can be changed after it is built
    public string $city;
}

// Good
final class AddressData extends Data
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
    ) {}
}
```

### new-data-object

`new SomeData(...)` instead of `::from()` or a named `fromX()` factory. `new` skips everything the package does:
casts, name mapping, nested hydration, validation.

```php
// Bad
$customer = new CustomerData($row['name'], $row['email']);   // ← no casts, no mapping, no validation

// Good
$customer = CustomerData::from($row);
```

### all-nullable-data

A Data class where every field is `?T` or has a default. The type says nothing is certain, so every reader has to
check everything. Type each field as what it really is.

```php
// Bad
final class GridData extends Data
{
    public function __construct(
        public readonly ?int $columns = null,      // ← a grid always has columns
        public readonly ?int $gap = null,
        public readonly ?string $align = null,
    ) {}
}

// Good
final class GridData extends Data
{
    public function __construct(
        public readonly int $columns = 1,
        public readonly int $gap = 0,
        public readonly Alignment $align = Alignment::Start,
    ) {}
}
```

### all-optional-data

Every field of a Data class is `T|Optional`. The type promises that nothing is ever present. What is optional is
the whole object: put `Optional` on the field that holds it, and give its own fields concrete types. (A partial
update, where each supplied field is applied by itself, may keep every field optional.)

```php
// Bad
final class GridData extends Data
{
    public function __construct(
        public readonly int|Optional $columns = new Optional(),   // ← every field can be missing
        public readonly int|Optional $gap = new Optional(),
    ) {}
}

// Good: the grid itself is what may be left out
final class LayoutData extends Data
{
    public function __construct(
        public readonly GridData|Optional $grid = new Optional(),
    ) {}
}
```

### nullable-wire-object

On a `#[TypeScript]` Data class, a nested Data or enum typed `T|null`. It sends `null` to the frontend, where the
frontend expects the key to be missing (`x?.`). Type it `T|Optional` so the key is left out.

```php
// Bad
public readonly ?UiChrome $chrome = null;                       // ← ships "chrome": null

// Good
public readonly UiChrome|Optional $chrome = new Optional();     // the key is left out
```

### null-to-optional-map

A producer that maps null onto `Optional` by hand with a ternary or `??`, at every place it builds the object. Put
that mapping in one named factory. (Spatie's own `optional()` maps null to null, which is the opposite.)

```php
// Bad
chrome: $row->chrome === null ? new Optional : UiChrome::from($row->chrome),   // ← repeated at every producer

// Good
chrome: UiChrome::optionalOrMissing($row->chrome),
```

### prefer-optional-create

A raw `new Optional` in a runtime expression. Use `Optional::create()`. Only a parameter or property default must
stay `new Optional()`, because a call is not allowed there.

```php
// Bad
return $coupon === null ? new Optional : $coupon;   // ← a runtime expression

// Good
return $coupon ?? Optional::create();
```

### data-collection-type

A property typed `DataCollection`. It generates broken TypeScript and its items are not hydrated by type. Type it
`array` (or `Collection`) and name the item type with `#[DataCollectionOf]`.

```php
// Bad
public readonly DataCollection $nodes;   // ← items of what?

// Good
#[DataCollectionOf(NodeData::class)]
public readonly array $nodes;
```

### manual-hydration-loop

A collection built with `::from()` for each item in a loop or a `map`. The package already does this.

```php
// Bad
$lines = array_map(fn (array $row) => OrderLineData::from($row), $rows);   // ← one ::from per item, by hand

// Good
$lines = OrderLineData::collect($rows);
```

### manual-input-cast

A value-object property that every caller builds by hand before calling `::from`. The mapping from the simple
value to the object is copied to each call site. Let a cast own it once.

```php
// Bad
InboundOrderData::from(['price' => new Money($request->integer('price'))]);   // ← every caller builds the Money

// Good
final class InboundOrderData extends Data
{
    public function __construct(
        #[WithCast(MoneyCast::class)]
        public readonly Money $price,
    ) {}
}

InboundOrderData::from($request);
```

### hook-missing-computed

A get-only property hook on a Data class without `#[Computed]`. Spatie reads the virtual property as an input and
expects it in `::from()`: it crashes, or the value is dropped.

```php
// Bad
public array $docks { get => $this->dockSet->all(); }   // ← read as a required input

// Good
#[Computed]
public array $docks { get => $this->dockSet->all(); }
```

### nested-type-missing-typescript

A `#[TypeScript]` Data class has a property typed as a nested Data class that is not `#[TypeScript]` itself. The
generator writes that property as `undefined`. Tag the nested class too. (An enum needs no tag.)

```php
// Bad
#[TypeScript]
final class OrderData extends Data
{
    public function __construct(public readonly CustomerData $customer) {}   // ← CustomerData has no #[TypeScript]
}

// Good
#[TypeScript]
final class CustomerData extends Data { … }
```

### data-method-hint-collision

A `@method` docblock tag that declares a method the class really has. The IDE now sees the method twice. A
`@method` hint is only for the magic `from` and `collect`.

```php
// Bad
/**
 * @method static array rules()            ← rules() is a real method below
 */
final class CouponData extends Data
{
    public static function rules(): array { … }
}

// Good
/**
 * @method static self from(array $payload)
 */
final class CouponData extends Data
{
    public static function rules(): array { … }
}
```

## Commands

- `{{binary}} judge --skill=php/spatie-data` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `all-nullable-data`, `all-optional-data`, `data-collection-type`, `data-method-hint-collision`, `hook-missing-computed`, `manual-hydration-loop`, `manual-input-cast`, `nested-type-missing-typescript`, `new-data-object`, `non-final-data`, `null-to-optional-map`, `nullable-wire-object`, `prefer-optional-create`.
- `{{binary}} scaffold --sin=<sin>` — generate the helper the fix reaches for, for `null-to-optional-map`.
- `{{binary}} report --detector=<Detector> --reason="…" --best-design="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`shared/fix-at-the-source`](../../shared/fix-at-the-source/SKILL.md) — a Data class IS a boundary; type it total so consumers don't re-validate. The all-nullable DTO is the canonical symptom-deferral.
- [`shared/absence`](../../shared/absence/SKILL.md) — owns the `Optional` vs `?T` vs default decision; this skill only gives the Spatie mechanics for each.
- [`shared/exceptions`](../../shared/exceptions/SKILL.md) — a required field missing at `::from()` should fail hard; surface it named when you catch the framework's exception at a tolerant boundary.
