# Value objects — give related data a type — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### array-bag

String-indexing (`$arr['key']`) a structured array param instead of giving it a name — the type was never defined.

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

// in Shop\Catalog\ImportRowNormalizer
// The same import row, given its type the moment it arrives: `ImportRow::from($row)` names the
// fields ONCE at the boundary, so nothing downstream reads `$row['sku']` off a loose array.

public function ingest(array $row): void
{
    $this->persist(ImportRow::from($row));
}

// The same row with its required fields non-nullable: `::from()` fails hard on a
// real miss, so a valid row can't be confused with a malformed one.

final class ImportRow extends Data
{
    public function __construct(
        public readonly string $sku,
        public readonly int $quantity,
        public readonly ?int $priceCents = null,
        public readonly ?string $note = null,
    ) {}

    public function lineTotal(): int
    {
        return $this->quantity * ($this->priceCents ?? 0);
    }
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

A class's own fields always change and get checked together — one concept split across several fields — and should be folded into a single value object.

```php
----------[ Bad ]----------

/*
 * Pattern: coupled optionals. `floor`/`ceil` are one price range — they are null-guarded as a PAIR and
 * then assembled together, so the illegal half-present state (floor set, ceil absent) is representable and
 * the guard only exists to reject it. They should be one `Range|null`.
 */
final class PriceBand
{
    public function __construct(
        public readonly ?int $floor = null,
        public readonly ?int $ceil = null,
        public readonly string $currency = 'EUR',
    ) {}

    public function window(): array
    {
        return $this->floor !== null && $this->ceil !== null ? [$this->floor, $this->ceil] : [];
    }
}

----------[ Good ]----------

/* The clump extracted: the two halves that only ever moved together ARE the range. */
final readonly class PriceRange
{
    public function __construct(
        public int $floor,
        public int $ceil,
    ) {}
}

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

A wither method rebuilds the whole object by re-listing every constructor field, so adding a new field means updating every wither in the class.

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

A value type that mutates its own field after construction, so two things holding what should be the same value can end up different — one changes without the other knowing.

```php
----------[ Bad ]----------

// A reading with its own scale, taken once and then re-scaled in place. The field is not promoted
// but the constructor still takes it, so it is what the caller ASKED for — and converting rewrites
// it, leaving anything that already recorded this reading describing a temperature nobody measured.

final class Temperature
{
    private float $degrees;

    private string $scale;

    public function __construct(float $degrees, string $scale)
    {
        $this->degrees = $degrees;
        $this->scale = $scale;
    }

    public function convertToCelsius(): void
    {
        $this->degrees = ($this->degrees - 32) * 5 / 9;
        $this->scale = 'C';
    }

    public function reading(): string
    {
        return round($this->degrees, 1) . '°' . $this->scale;
    }

    public function belowFreezing(): bool
    {
        return $this->scale === 'C' ? $this->degrees < 0 : $this->degrees < 32;
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

// in Shop\Checkout\CheckoutUnpacker
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

/* The tuple named: the four values that were positional now answer to what they are. */
final readonly class CheckoutReference
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public string $order,
        public array $lines,
        public string $currency,
    ) {}

    public function lineCount(): int
    {
        return count($this->lines);
    }
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

A `#[TypeScript]` `Data` class spreads a value object it already models flat across sibling scalar fields sharing a camelCase prefix (`wireType` + `wireLabel`) instead of nesting the existing `Wire{type, label}`.

```php
----------[ Bad ]----------

/*
 * PortView restates the existing Wire value object FLAT as wire{Type,Socket,Label} instead of nesting a
 * single `wire: Wire`. The flat trio travels to the frontend and should be one depth-nested sub-object.
 */
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

/* The Wire value object: a port's wiring identity, {type, socket, label}. */
#[TypeScript]
final class Wire extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly string $socket,
        public readonly string $label,
    ) {}
}
```
