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
public function ingest(array $row): void
{
    $this->persist(ImportRow::from($row));
}

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

### array-bag-parameter

A parameter declared `array` that callers fill with string-keyed records (`['mode' => …, 'plan' => …]`): the method never defined the type its fields belong to.

```php
----------[ Bad ]----------

public function push(array $job): void
{
    $this->spool($job);
}

----------[ Good ]----------

// in Shop\Courier\Printing\LabelQueue
public function pushJob(LabelJob $job): void
{
    $this->spooled[] = $job->line();
}

final readonly class LabelJob
{
    public function __construct(
        public string $tracking,
        public int $copies,
        public string $format,
    ) {}

    public function line(): string
    {
        return sprintf('%s x%d (%s)', $this->tracking, $this->copies, $this->format);
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
public function dailyReport(int $day): DailyReport
{
    $gross = $this->orders->grossForDay($day);

    return new DailyReport(
        gross: $gross,
        net: (int) round($gross * 0.79),
    );
}

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

final readonly class PriceRange
{
    public function __construct(
        public int $floor,
        public int $ceil,
    ) {}
}

final class BandedPrice
{
    public function __construct(
        public readonly ?PriceRange $range = null,
        public readonly string $currency = 'EUR',
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

public function withOrder(int $order): self
{
    return clone($this, ['order' => $order]);
}
```

### mutable-value-object

A value type that mutates its own field after construction, so two things holding what should be the same value can end up different — one changes without the other knowing.

```php
----------[ Bad ]----------

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
public function parse(string $reference): CheckoutReference
{
    $parts = explode(':', $reference);

    return new CheckoutReference(
        order: $parts[0],
        lines: array_slice($parts, 1),
        currency: strtoupper(substr($parts[0], 0, 3)),
    );
}

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

### flat-field-cluster

A `#[TypeScript]` `Data` class spreads a value object it already models flat across sibling scalar fields sharing a camelCase prefix (`wireType` + `wireLabel`) instead of nesting the existing `Wire{type, label}`.

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

#[TypeScript]
final class NestedPortView extends Data
{
    public function __construct(
        public readonly Wire $wire,
        public readonly int $index,
    ) {}
}

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
