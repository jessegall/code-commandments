---
name: commandments-backend-value-objects
description: "WHEN to give data a type instead of passing it loose — an `array<string,mixed>` bag, 3+ values that always travel together (a data clump), a string-indexed structured array, primitive obsession, or a too-long parameter list all want a typed object. Read this BEFORE you pass or return an untyped array, add another parameter to a crowded signature, or write `$arr['key']` on a structured array. (How to WRITE the class is `spatie-data`; this is when to make one.)"
---

# Value objects — give related data a type

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> Data that travels together is a **thing**, not a loose pile of arrays and primitives. The moment a
> cluster of values is passed around, returned, or reached into by string keys, it wants a name and a type.

## The principle

Data that travels together is a **thing**, not a loose pile of arrays and primitives. The moment a cluster
of values is passed around, returned, or reached into by string keys, it wants a name and a type — the type
IS the documentation, the validation, and the contract, all enforced by the compiler instead of by every
reader's memory.

Reach for a type the moment you are about to: pass or return an `array<string, mixed>` keyed bag (its keys
are an undocumented contract — make them a type); thread three-or-more values that always travel together —
a *data clump* wearing separate parameter slots; reach into a structured array by string key
(`$entry['title']`) — a typed object that hasn't been born yet; grow an already-crowded signature (group
the related arguments into one object instead of adding the fourth); or pass a bare primitive that is really
a concept — a `string $email`, a `string $currency` + `int $amount`, a `string $key` with format rules → a
value object that owns its own validation.

Introduce the type **where the data is born** — at the boundary that first receives it, the method that
first assembles it — not three frames downstream after it has been threaded around as a bag. A value object
introduced late just relabels data everyone already mishandled. This is fix-at-the-source applied to shape.

## Rules

- Give a structured array a typed value object — never read a named field by string key off an `array` param.
  _A Spatie `Data` object built via `::from($array)`._
- Return a typed value object, not a multi-field string-keyed array literal.
  _Return a Spatie `Data` object via `::from(...)`._
- Fields that move as a unit are one type: extract the clump into a value object and hold THAT; never mirror a datum that already lives on a nested object.
  _Fold the co-moving fields into one value object (name the existing type when the clump already is one); drop a field that duplicates a nested object's property._
- Bundle values that always travel together into one object; don't thread 3+ of them as separate params.
  _A value object the params fold into (`Money::of()`, `NodePosition`)._
- A wither changes ONE thing: say only what changes. `clone($this, ['x' => $x])` states the intent; re-listing every field states the constructor again, N times over.
  _Replace `new self($this->a, $this->b, $changed)` with `clone($this, ['c' => $changed])` — `repent` does it for you._
- Make a value immutable: build it complete and derive a NEW one to change it; never write its fields after construction.
  _`readonly` on the class, and a `with…()`/named derivation that returns a new instance (PHP 8.5's `clone with`)._
- Return a typed object, not a positional tuple `[$a, $b, $c]` the caller destructures by position.
  _A small `readonly` result object._
- Return a typed object from a decoded boundary; never hand back a raw `json_decode(...)` array.
  _Decode into a Spatie `Data` object: `X::from(json_decode(...))`._
- When scalar fields on a Data class share a prefix that names a value object the codebase already declares, they restate that object flat. Nest them into the existing sub-object and shed the prefix — `wireType`/`wireLabel` become `wire: Wire{type, label}`.
  _Replace the prefixed siblings with a single nested property typed as the existing value object, dropping the prefix from each member._

## Bad → good

### array-bag

String-indexing (`$arr['key']`) a structured array param (an unborn type)

```php
----------[ Bad ]----------

public function normalize(array $row): void
{
    $this->products->upsert(
        $row['sku'] ?? '',
        $row['name'] ?? '',
        (int) ($row['stock'] ?? 0),
    );
}

----------[ Good ]----------

// The same import row, given its type the moment it arrives: `ImportRow::from($row)` names the
// fields ONCE at the boundary, so nothing downstream reads `$row['sku']` off a loose array.

public function ingest(array $row): void
{
    $this->persist(ImportRow::from($row));
}
```

### array-return-bag

Returning a multi-field string-keyed array literal (a bag that should be a value object)

```php
----------[ Bad ]----------

public function daily(int $day): array
{
    $currency = config('shop.currency');
    $gross = $this->orders->grossForDay($day);

    return [
        'currency' => $currency,
        'gross' => $gross,
        'net' => (int) round($gross * 0.79),
    ];
}

----------[ Good ]----------

// The same daily figures as a typed report value object — named fields, not a
// loose string-keyed bag.

public function dailyReport(int $day): DailyReport
{
    $gross = $this->orders->grossForDay($day);

    return new DailyReport(
        gross: $gross,
        net: (int) round($gross * 0.79),
    );
}
```

### coupled-fields

A class's own fields always travel together — one concept masquerading as several fields, guards, and reaches — and should be a single value object

```php
----------[ Bad ]----------

final class ShelfPlan
{
    public function __construct(
        public readonly Bay $anchor,
        public readonly Fixture $neighbour,
    ) {}

    public function run(): array
    {
        return [$this->anchor, $this->neighbour->slot];
    }

    public function labelled(): string
    {
        return $this->describe($this->anchor, $this->neighbour->slot);
    }

    private function describe(Bay $from, Bay $to): string
    {
        return "{$from->aisle}-{$to->aisle}";
    }
}

----------[ Good ]----------

// The same band holding the value object instead of its parts: one `PriceRange|null` says "banded or not"
// in one field, so the half-present state (floor set, ceil absent) cannot be spelled and the pair-guard
// that existed only to reject it is gone.

final class BandedPrice
{
    public function __construct(
        public readonly ?PriceRange $range = null,
        public readonly string $currency = 'EUR',
    ) {}
}
```

### data-clump

The same 3+ scalar params threaded through 2+ classes (a recurring data clump → one object)

```php
----------[ Bad ]----------

public function record(string $shopId, string $userId, string $channelId): string
{
    return implode(self::SEPARATOR, [$shopId, $userId, $channelId]);
}

----------[ Good ]----------

// The clump named: one value object carries the three fields that travelled
// together.

public function recordAccess(AccessContext $context): string
{
    return implode(self::SEPARATOR, [$context->shopId, $context->userId, $context->channelId]);
}
```

### hand-rolled-wither

A wither rebuilds its object by re-spelling every constructor field, so each new field must be threaded through N of them

```php
----------[ Bad ]----------

public function withValue(?string $value): self
{
    return new self($this->id, $this->label, $this->type, $this->required, $value, $this->order);
}

----------[ Good ]----------

// The wither saying ONLY what changes: `clone($this, ['order' => $order])` states the intent, so a
// seventh field never touches this method — and the constructor is stated once, not N times over.

public function withOrder(int $order): self
{
    return clone($this, ['order' => $order]);
}
```

### mutable-value-object

a value type that writes its own field after construction — two holders of the same value, and one of them can change it under the other

```php
----------[ Bad ]----------

// A postal address that relocates itself. Whatever recorded "the address this parcel was quoted
// for" now holds a different address, and nothing near the quote did that.

final class PostalAddress
{
    public function __construct(
        private string $line,
        private string $postcode,
        private string $city,
        private string $country,
    ) {}

    public function relocate(string $line, string $postcode, string $city): void
    {
        $this->line = $line;
        $this->postcode = $postcode;
        $this->city = $city;
    }

    public function oneLine(): string
    {
        return "{$this->line}, {$this->postcode} {$this->city}, {$this->country}";
    }

    public function isDomestic(): bool
    {
        return $this->country === 'NL';
    }

    public function label(): array
    {
        return [$this->line, "{$this->postcode} {$this->city}", strtoupper($this->country)];
    }
}

----------[ Good ]----------

// The same reading, derived instead of re-scaled: `readonly` on the CLASS makes it final once built, and
// `withCelsius()` answers with a NEW reading — so whatever recorded this one still describes the
// temperature that was actually measured.

final readonly class CalibratedReading
{
    public function __construct(
        public float $degrees,
        public string $scale,
    ) {}

    public function withCelsius(): self
    {
        return new self(($this->degrees - 32) * 5 / 9, 'C');
    }
}
```

### positional-tuple-return

Returning a positional TUPLE — `return [$node, $key, $inputs, $outputs]` — bundling independent values as a keyless list the caller destructures by position

```php
----------[ Bad ]----------

public function unpack(string $reference): array
{
    $parts = explode(':', $reference);
    $order = $parts[0];
    $lines = array_slice($parts, 1);
    $count = count($lines);
    $currency = strtoupper(substr($order, 0, 3));

    return [$order, $lines, $count, $currency];
}

----------[ Good ]----------

// The same reference, answered with a named result: the caller reads `->currency`, not `[3]`, so
// adding a field never silently re-numbers what everyone else destructured.

public function parse(string $reference): CheckoutReference
{
    $parts = explode(':', $reference);

    return new CheckoutReference(
        order: $parts[0],
        lines: array_slice($parts, 1),
        currency: strtoupper(substr($parts[0], 0, 3)),
    );
}
```

### raw-decoded-array-return

Returning a raw decoded boundary array (`json_decode(...)`) untyped

```php
----------[ Bad ]----------

public function rates(string $base, array $symbols): array
{
    $query = http_build_query([
        'base' => $base,
        'symbols' => implode(',', $symbols),
    ]);

    return json_decode($this->http->get("https://fx.test/latest?{$query}"), true);
}

----------[ Good ]----------

public function ratesTyped(string $base, array $symbols): RateTable
{
    $query = http_build_query([
        'base' => $base,
        'symbols' => implode(',', $symbols),
    ]);

    return RateTable::from(json_decode($this->http->get("https://fx.test/latest?{$query}"), true));
}
```

### flat-field-cluster

A `#[TypeScript]` `Data` class spreads a value object it already models flat across sibling scalar fields sharing a camelCase prefix (`wireType` + `wireLabel`) instead of NESTING the existing `Wire{type, label}` — width instead of depth

```php
----------[ Bad ]----------

#[TypeScript]
final class PortView extends Data
{
    public function __construct(
        public readonly string $wireType,
        public readonly string $wireSocket,
        public readonly string $wireLabel,
        public readonly int $index,
    ) {}

    public function slot(): string
    {
        return $this->wireSocket . '#' . $this->index;
    }

    public function isBus(): bool
    {
        return $this->wireType === 'bus' || $this->wireType === 'backplane';
    }

    public function ordinal(): string
    {
        return match (true) {
            $this->index === 0 => 'primary',
            $this->index < 4 => 'secondary',
            default => 'auxiliary',
        };
    }

    public function pinout(string $prefix): string
    {
        return strtoupper($prefix) . '/' . $this->wireLabel . '/' . $this->slot();
    }
}

----------[ Good ]----------

// The same view with its depth restored: the wire{Type,Socket,Label} trio is NESTED as the one `Wire` the
// codebase already declares, so each member sheds the prefix and the value object is modelled once.

#[TypeScript]
final class NestedPortView extends Data
{
    public function __construct(
        public readonly Wire $wire,
        public readonly int $index,
    ) {}
}
```

## When it fires

- String-indexing (`$arr['key']`) a structured array param (an unborn type) — `ArrayBagDetector`
- Returning a multi-field string-keyed array literal (a bag that should be a value object) — `ArrayReturnBagDetector`
- A class's own fields always travel together — one concept masquerading as several fields, guards, and reaches — and should be a single value object — `CoupledFieldsDetector`
- The same 3+ scalar params threaded through 2+ classes (a recurring data clump → one object) — `DataClumpDetector`
- A wither rebuilds its object by re-spelling every constructor field, so each new field must be threaded through N of them — `HandRolledWitherDetector`
- a value type that writes its own field after construction — two holders of the same value, and one of them can change it under the other — `MutableValueObjectDetector`
- Returning a positional TUPLE — `return [$node, $key, $inputs, $outputs]` — bundling independent values as a keyless list the caller destructures by position — `PositionalTupleReturnDetector`
- Returning a raw decoded boundary array (`json_decode(...)`) untyped — `RawDecodedArrayReturnDetector`
- A `#[TypeScript]` `Data` class spreads a value object it already models flat across sibling scalar fields sharing a camelCase prefix (`wireType` + `wireLabel`) instead of NESTING the existing `Wire{type, label}` — width instead of depth — `FlatFieldClusterDetector`

## Checklist

- [ ] Give a structured array a typed value object — never read a named field by string key off an `array` param.
- [ ] Return a typed value object, not a multi-field string-keyed array literal.
- [ ] Fields that move as a unit are one type: extract the clump into a value object and hold THAT; never mirror a datum that already lives on a nested object.
- [ ] Bundle values that always travel together into one object; don't thread 3+ of them as separate params.
- [ ] A wither changes ONE thing: say only what changes. `clone($this, ['x' => $x])` states the intent; re-listing every field states the constructor again, N times over.
- [ ] Make a value immutable: build it complete and derive a NEW one to change it; never write its fields after construction.
- [ ] Return a typed object, not a positional tuple `[$a, $b, $c]` the caller destructures by position.
- [ ] Return a typed object from a decoded boundary; never hand back a raw `json_decode(...)` array.
- [ ] When scalar fields on a Data class share a prefix that names a value object the codebase already declares, they restate that object flat. Nest them into the existing sub-object and shed the prefix — `wireType`/`wireLabel` become `wire: Wire{type, label}`.

## Related skills

- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — introduce the type where the data is born, not downstream.
- [`backend/spatie-data`](../spatie-data/SKILL.md) — once you've decided it's a DTO, that skill is *how* to write it (and its honest-field-types rule keeps the new type from being a fresh all-nullable bag).
- [`backend/absence`](../absence/SKILL.md) — the new type's fields still answer "can this be missing?" honestly.
