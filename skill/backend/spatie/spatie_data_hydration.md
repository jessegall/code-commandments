> `::from()` already knows how to turn plain input into a Data object: nested objects, collections, enums, dates and
> renamed keys. Hand it the plain input and let it work.
> Read this before you build the array you pass to `::from([...])`, and when a spatie-data-hydration finding points
> here.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Pass `::from` the raw value for each slot. Whatever you build by hand there, the package would have built for you.**

`::from` looks at the type of every property and hydrates it:

| The property is typed | Pass | Not |
|---|---|---|
| a nested `Data` class | the plain array (or the object you already hold) | `Nested::from([...])` |
| `#[DataCollectionOf(X::class)] array` | the list of plain arrays | a `map` of `X::from` |
| a backed enum | the enum, or its raw value | `Enum::from($raw)`, `$enum->value` |
| a `DateTimeInterface` | the string | `new DateTime($raw)` |
| a value object | the raw value, with a `#[WithCast]` on the property | the object, built at every caller |

And when the input uses other key names (snake_case from a database or an API), say so once on the class with
`#[MapInputName]`, not key by key at the call site.

Every hand-built step is work done twice, and a place where the caller's idea of the shape can differ from the
class's. The call site should read `SomeData::from($source)`.

## The sins

### redundant-nested-from

A nested `X::from([...])` in a slot that the parent `::from` hydrates anyway. Pass the plain array.

```php
// Bad
OrderData::from([
    'number' => $row['number'],
    'customer' => CustomerData::from($row['customer']),   // ← the parent ::from does this
]);

// Good
OrderData::from([
    'number' => $row['number'],
    'customer' => $row['customer'],
]);
```

### redundant-native-cast

An enum or a date constructed at the hydration site, where the property casts the raw value by itself.

```php
// Bad
OrderData::from([
    'status' => OrderStatus::from($row['status']),   // ← the enum slot casts the string
    'paidAt' => new DateTimeImmutable($row['paid_at']),
]);

// Good
OrderData::from([
    'status' => $row['status'],
    'paidAt' => $row['paid_at'],
]);
```

### redundant-enum-unwrap

An enum unwrapped to `->value` for a slot typed as that enum. Spatie casts the scalar straight back.

```php
// Bad
OrderData::from(['status' => $order->status->value]);   // ← unwrapped, only to be wrapped again

// Good
OrderData::from(['status' => $order->status]);
```

### data-to-array-roundtrip

A Data object turned into an array with `->toArray()` and put in a slot that hydrates it again: build, flatten,
build. Pass the object.

```php
// Bad
InvoiceData::from(['customer' => CustomerData::from($customer)->toArray()]);   // ← built, flattened, built again

// Good
InvoiceData::from(['customer' => $customer]);
```

### hand-key-remap

A `::from([...])` that only renames keys by hand, from `$src['snake_key']` to `camelKey`. Declare the mapping once
on the class.

```php
// Bad
CustomerData::from([
    'firstName' => $row['first_name'],   // ← a rename, key by key
    'lastName' => $row['last_name'],
    'postalCode' => $row['postal_code'],
]);

// Good
#[MapInputName(SnakeCaseMapper::class)]
final class CustomerData extends Data { … }

CustomerData::from($row);
```

### derived-collection-cast

A `#[DataCollectionOf]` slot filled by mapping a factory over the inputs at the call site. Every caller has to know
how an item is derived. Put the derivation in a cast on the property and pass the raw list.

```php
// Bad
MenuData::from(['items' => array_map(fn (string $slug) => MenuItem::for($slug), $slugs)]);   // ← derived by the caller

// Good
final class MenuData extends Data
{
    public function __construct(
        #[WithCast(MenuItemsCast::class)]
        public readonly array $items,
    ) {}
}

MenuData::from(['items' => $slugs]);
```
