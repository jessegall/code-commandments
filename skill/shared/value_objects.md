> Data that belongs together gets a type with a name. A loose bag of keys, a list read by position, or the same four
> parameters travelling side by side are all a type nobody wrote down.
> Read this before you pass or return a map with string keys, return several values as a list, add a parameter that
> always travels with two others, or write a method that changes a value in place, and when a value-objects finding
> points here.
> The examples are pseudocode: read them as the language you are writing.
> An arrow in an example (`←` or `↑`) marks the line the lesson is about.

## The principle

**Give related data a type, build it complete, and never change it afterwards.**

A value object is a small immutable type for one concept: a `Money`, a `Period`, an `Address`, a `ParsedLine`. It
does three things a bag of loose values cannot:

1. **It names the concept.** `period` says more than `from` and `until`, and the compiler knows the two belong together.
2. **It is checked once.** The fields are validated where the object is built, so every reader after that can rely
   on them. A string key can be misspelled at every read.
3. **It stays what it was.** A value is built complete and never written again. To change it you derive a new one, so two
   places holding "the same" value can never end up holding different ones.

```pseudo
// Bad: a concept with no type; every reader must know the keys and trust they are there
function parse(line): map {
    return { "sku": parts[0], "quantity": int(parts[1]), "price": int(parts[2]) }
}

total = parse(line)["quantity"] * parse(line)["price"]

// Good
class OrderLine {
    readonly sku: string
    readonly quantity: int
    readonly price: Money

    function total(): Money { return this.price.times(this.quantity) }
}

total = OrderLine.parse(line).total()
```

## The sins

### array-bag

A named field read by string key off a loose map parameter. The record has a shape, and the shape was never given a
type. Build a value object once from the incoming map, and read fields from that.

```pseudo
// Bad
function label(shipment: map): string {
    return shipment["carrier"] + " " + shipment["tracking"]   // ← keys that no type declares
}

// Good
function label(shipment: Shipment): string {
    return shipment.carrier + " " + shipment.tracking
}
```

### array-bag-parameter

A parameter typed as a loose map that every caller fills with the same string keys. The fault is in the definition,
not in the callers: the method never said what it takes.

```pseudo
// Bad
function schedule(options: map) { … }   // ← which keys? the signature does not say

schedule({ "at": tomorrow, "retries": 3, "queue": "mail" })

// Good
function schedule(delivery: Delivery) { … }

schedule(Delivery(at: tomorrow, retries: 3, queue: "mail"))
```

### array-return-bag

A function that returns a literal map with several string keys. The caller receives a bag and has to remember its
keys. Return a value object with those fields.

```pseudo
// Bad
function summary(order): map {
    return { "lines": order.lines.count, "total": order.total, "currency": order.currency }
    // ↑ the caller must remember these keys
}

// Good
function summary(order): OrderSummary {
    return OrderSummary(lines: order.lines.count, total: order.total, currency: order.currency)
}
```

### positional-tuple-return

Several independent values returned as a keyless list that the caller takes apart by position. Swap two of them and
nothing complains. Return a small typed result.

```pseudo
// Bad
function resolve(path) {
    return [node, key, inputs, outputs]   // ← four values, told apart only by position
}

[node, key, inputs, outputs] = resolve(path)

// Good
function resolve(path): Resolved {
    return Resolved(node, key, inputs, outputs)
}

resolved = resolve(path)
```

### data-clump

The same group of scalar parameters appears on several types or functions. They are one value that has not been
introduced yet. Give the group a type and pass that.

```pseudo
// Bad
function book(street, houseNumber, postcode, city) { … }   // ← the same four parameters
function quote(street, houseNumber, postcode, city, weight) { … }
function label(street, houseNumber, postcode, city) { … }   // ← on every function: they are one Address

// Good
function book(address: Address) { … }
function quote(address: Address, weight) { … }
function label(address: Address) { … }
```

### coupled-fields

Fields of one class that always change together and are always checked together. They are one concept split across
several fields. Fold them into a value object that owns the rule between them.

```pseudo
// Bad
class Shift {
    opensAt: int
    closesAt: int   // ← always read and changed together with opensAt

    function stretch(from, until) {
        if (from < this.opensAt || until > this.closesAt) {
            this.opensAt = min(from, this.opensAt)
            this.closesAt = max(until, this.closesAt)
        }
    }
}

// Good
class Shift {
    hours: Period

    function stretch(period: Period) {
        this.hours = this.hours.covering(period)
    }
}
```

### mutable-value-object

A value type that writes its own field after construction. Two holders of what should be the same value can now end
up different, and neither knows. Make the type immutable and derive a new one to change it.

```pseudo
// Bad
class Money {
    amount: int

    function add(other: Money) {
        this.amount = this.amount + other.amount   // ← changes a value someone else may be holding
    }
}

// Good
class Money {
    readonly amount: int

    function plus(other: Money): Money {
        return Money(this.amount + other.amount)
    }
}
```

### hand-rolled-wither

A `with…` method that rebuilds the whole object by listing every constructor field again. Add a field, and every
wither in the class must be updated, or it silently drops the new field. A wither says only what changes: use the
language's copy-with-change.

```pseudo
// Bad
function withValue(value): Socket {
    return Socket(this.id, this.label, this.type, this.required, this.order, value)
    // ↑ lists every field again; a new field must be added here too
}

// Good
function withValue(value): Socket {
    return copy(this, value: value)
}
```

### flat-field-cluster — in PHP

A `#[TypeScript]` `Data` class that spreads a value object it already models flat across sibling fields sharing a
prefix. The prefix names the object that should be nested.

```php
// Bad
final class PortView extends Data
{
    public function __construct(
        public readonly string $wireType,   // ← three fields with one prefix: a Wire, spread flat
        public readonly string $wireSocket,
        public readonly string $wireLabel,
        public readonly int $index,
    ) {}
}

// Good
final class PortView extends Data
{
    public function __construct(
        public readonly Wire $wire,
        public readonly int $index,
    ) {}
}
```

## In your language

- **PHP.** A `final readonly class` with promoted constructor properties; copy-with-change is `clone($this, [...])`.
  For data that crosses a boundary, a Spatie `Data` class (see `spatie-data`).
- **Python.** `@dataclass(frozen=True)`; copy-with-change is `dataclasses.replace(value, field=new)`. A `TypedDict` is
  still a bag: prefer the dataclass.
- **C#.** A `record` (or `readonly record struct`); copy-with-change is `value with { Field = next }`.
- **TypeScript.** An interface or class with `readonly` fields; copy-with-change is `{ ...value, field: next }`. A
  returned tuple `[a, b, c]` is the positional return.
- **Go.** A struct passed by value; return a struct, not several loosely related results beyond `value, err`.
