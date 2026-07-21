---
name: commandments-backend-value-objects
description: WHEN to give data a type instead of passing it loose — an `array<string,mixed>` bag, 3+ values that always travel together (a data clump), a string-indexed structured array, primitive obsession, or a too-long parameter list all want a typed object. Read this BEFORE you pass or return an untyped array, add another parameter to a crowded signature, or write `$arr['key']` on a structured array. (How to WRITE the class is `spatie-data`; this is when to make one.)
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
- Return a typed object, not a positional tuple `[$a, $b, $c]` the caller destructures by position.
  _A small `readonly` result object._
- Return a typed object from a decoded boundary; never hand back a raw `json_decode(...)` array.
  _Decode into a Spatie `Data` object: `X::from(json_decode(...))`._
- When scalar fields on a Data class share a prefix that names a value object the codebase already declares, they restate that object flat. Nest them into the existing sub-object and shed the prefix — `wireType`/`wireLabel` become `wire: Wire{type, label}`.
  _Replace the prefixed siblings with a single nested property typed as the existing value object, dropping the prefix from each member._

## Bad → good

```php
// Bad
public function normalize(array $row): void
{
    $this->products->upsert(
        $row['sku'] ?? '',
        $row['name'] ?? '',
        (int) ($row['stock'] ?? 0),
    );
}

// Good
public function __unserialize(array $data): void
{
    $this->pendingSku = $data['sku'];
    $this->pendingStock = $this->migrateStock($data);
}
```

```php
// Bad
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

// Good
public function dailyReport(int $day): DailyReport
{
    $gross = $this->orders->grossForDay($day);

    return new DailyReport(
        gross: $gross,
        net: (int) round($gross * 0.79),
    );
}
```

```php
// Bad
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

// Good
final class OrderContext
{
    public function __construct(
        public readonly Shopper $shopper,
        public readonly Voucher $voucher,
    ) {}

    public function heading(): array
    {
        return [$this->shopper, $this->voucher->code];
    }

    public function footer(): array
    {
        return [$this->shopper, $this->voucher->code];
    }
}
```

```php
// Bad
public function record(string $shopId, string $userId, string $channelId): string
{
    return implode(self::SEPARATOR, [$shopId, $userId, $channelId]);
}

// Good
public function recordAccess(AccessContext $context): string
{
    return implode(self::SEPARATOR, [$context->shopId, $context->userId, $context->channelId]);
}
```

```php
// Bad
public function withValue(?string $value): self
{
    return new self($this->id, $this->label, $this->type, $this->required, $value, $this->order);
}

// Good
public function withOrder(int $order): self
{
    return clone($this, ['order' => $order]);
}
```

```php
// Bad
public function partition(array $rows): array
{
    $valid = [];
    $invalid = [];
    $errors = [];

    foreach ($rows as $row) {
        if ($row === '') {
            $errors[] = 'empty row';
        } elseif (str_contains($row, ';')) {
            $valid[] = $row;
        } else {
            $invalid[] = $row;
        }
    }

    return [$valid, $invalid, $errors];
}

// Good
public function partitionTyped(array $rows): Partitioned
{
    $valid = [];
    $invalid = [];
    $errors = [];

    foreach ($rows as $row) {
        if ($row === '') {
            $errors[] = 'empty row';
        } elseif (str_contains($row, ';')) {
            $valid[] = $row;
        } else {
            $invalid[] = $row;
        }
    }

    return new Partitioned($valid, $invalid, $errors);
}
```

```php
// Bad
public function rates(string $base, array $symbols): array
{
    $query = http_build_query([
        'base' => $base,
        'symbols' => implode(',', $symbols),
    ]);

    return json_decode($this->http->get("https://fx.test/latest?{$query}"), true);
}

// Good
public function ratesTyped(string $base, array $symbols): RateTable
{
    $query = http_build_query([
        'base' => $base,
        'symbols' => implode(',', $symbols),
    ]);

    return RateTable::from(json_decode($this->http->get("https://fx.test/latest?{$query}"), true));
}
```

```php
// Bad
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

// Good
#[TypeScript]
final class LooseCard extends Data
{
    public function __construct(
        public readonly string $metaTitle,
        public readonly int $metaCount,
    ) {}
}
```

## When it fires

- String-indexing (`$arr['key']`) a structured array param (an unborn type) — `ArrayBagDetector`
- Returning a multi-field string-keyed array literal (a bag that should be a value object) — `ArrayReturnBagDetector`
- A class's own fields always travel together — one concept masquerading as several fields, guards, and reaches — and should be a single value object — `CoupledFieldsDetector`
- The same 3+ scalar params threaded through 2+ classes (a recurring data clump → one object) — `DataClumpDetector`
- A wither rebuilds its object by re-spelling every constructor field, so each new field must be threaded through N of them — `HandRolledWitherDetector`
- Returning a positional TUPLE — `return [$node, $key, $inputs, $outputs]` — bundling independent values as a keyless list the caller destructures by position — `PositionalTupleReturnDetector`
- Returning a raw decoded boundary array (`json_decode(...)`) untyped — `RawDecodedArrayReturnDetector`
- A `#[TypeScript]` `Data` class spreads a value object it already models flat across sibling scalar fields sharing a camelCase prefix (`wireType` + `wireLabel`) instead of NESTING the existing `Wire{type, label}` — width instead of depth — `FlatFieldClusterDetector`

## Checklist

- [ ] Give a structured array a typed value object — never read a named field by string key off an `array` param.
- [ ] Return a typed value object, not a multi-field string-keyed array literal.
- [ ] Fields that move as a unit are one type: extract the clump into a value object and hold THAT; never mirror a datum that already lives on a nested object.
- [ ] Bundle values that always travel together into one object; don't thread 3+ of them as separate params.
- [ ] A wither changes ONE thing: say only what changes. `clone($this, ['x' => $x])` states the intent; re-listing every field states the constructor again, N times over.
- [ ] Return a typed object, not a positional tuple `[$a, $b, $c]` the caller destructures by position.
- [ ] Return a typed object from a decoded boundary; never hand back a raw `json_decode(...)` array.
- [ ] When scalar fields on a Data class share a prefix that names a value object the codebase already declares, they restate that object flat. Nest them into the existing sub-object and shed the prefix — `wireType`/`wireLabel` become `wire: Wire{type, label}`.

## Related skills

- [`backend/fix-at-the-source`](../fix-at-the-source/SKILL.md) — introduce the type where the data is born, not downstream.
- [`backend/spatie-data`](../spatie-data/SKILL.md) — once you've decided it's a DTO, that skill is *how* to write it (and its honest-field-types rule keeps the new type from being a fresh all-nullable bag).
- [`backend/absence`](../absence/SKILL.md) — the new type's fields still answer "can this be missing?" honestly.
