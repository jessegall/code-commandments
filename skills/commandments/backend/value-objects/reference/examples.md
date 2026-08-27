# Value objects — give related data a type — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

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

// in Shop\Reporting\SalesReport
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

// What the string-keyed bag became. The keys a caller used to guess at are fields, so a typo is a
// failure here rather than a null three layers down.

final class DailyReport
{
    public function __construct(
        public readonly int $gross,
        public readonly int $net,
    ) {}
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

// in Shop\Reporting\AccessAuditor
// The clump named: one value object carries the three fields that travelled
// together.

public function recordAccess(AccessContext $context): string
{
    return implode(self::SEPARATOR, [$context->shopId, $context->userId, $context->channelId]);
}

// The three ids that always travelled together, named once. Signatures shrink to one parameter and
// nothing can pass them in the wrong order any more.

final class AccessContext
{
    public function __construct(
        public readonly string $shopId,
        public readonly string $userId,
        public readonly string $channelId,
    ) {}
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

// in Shop\Integrations\ExchangeRateClient
public function ratesTyped(string $base, array $symbols): RateTable
{
    $query = http_build_query([
        'base' => $base,
        'symbols' => implode(',', $symbols),
    ]);

    return RateTable::from(json_decode($this->http->get("https://fx.test/latest?{$query}"), true));
}

// The shape the wire actually has, declared. A decoded response handed back raw asks every caller
// to know the payload's keys; this states them once, at the boundary that read them.

final class RateTable
{
    /**
     * @param  array<string, float>  $rates
     */
    public function __construct(
        public readonly string $base,
        public readonly array $rates,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function from(array $payload): self
    {
        return new self(
            base: (string) $payload['base'],
            rates: $payload['rates'],
        );
    }
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
