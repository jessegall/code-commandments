# Spatie Data — let the class build itself — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### all-nullable-data

All-nullable "god" DTO — every field `?T`/defaulted (type doesn't tell the truth)

```php
----------[ Bad ]----------

// A row from the legacy CSV importer — every field nullable, so a malformed row is
// indistinguishable from a valid one and every consumer must re-validate.

final class LegacyImportRow extends Data
{
    public function __construct(
        public readonly ?string $sku = null,
        public readonly ?int $quantity = null,
        public readonly ?int $priceCents = null,
        public readonly ?string $note = null,
    ) {}

    public function lineTotal(): int
    {
        return ($this->quantity ?? 0) * ($this->priceCents ?? 0);
    }
}

----------[ Good ]----------

// The FIX for {@see LegacyImportRow}: every field retyped to the truth. The three the importer cannot
// work without are non-nullable and undefaulted, so `::from()` fails hard on a malformed row; the one
// that is GENUINELY absent-or-present is `string|Optional = new Optional()`, which the wire OMITS
// rather than shipping as `null`. No `?? 0` is needed anywhere downstream.

final class RetypedImportRow extends Data
{
    public function __construct(
        public readonly string $sku,
        public readonly int $quantity,
        public readonly int $priceCents,
        public readonly string|Optional $note = new Optional(),
    ) {}

    public function lineTotal(): int
    {
        return $this->quantity * $this->priceCents;
    }

    public function annotated(): string
    {
        return $this->note instanceof Optional ? $this->sku : $this->sku . ' — ' . $this->note;
    }
}
```

### all-optional-data

Every field of a `Data` object is `T|Optional` — the type promises nothing is ever present; the absence belongs on the CONTAINER field where it's used

```php
----------[ Bad ]----------

final class GridBox extends Data
{
    public function __construct(
        public readonly int|Optional $columns = new Optional(),
        public readonly int|Optional $span = new Optional(),
        public readonly int|Optional $gap = new Optional(),
    ) {}

    public function template(): string
    {
        $cols = $this->columns instanceof Optional ? 1 : $this->columns;
        $gap = $this->gap instanceof Optional ? 0 : $this->gap;

        return "grid-template-columns: repeat({$cols}, 1fr); gap: {$gap}px";
    }

    public function area(int $rows): int
    {
        $cols = $this->columns instanceof Optional ? 1 : $this->columns;

        return $rows * $cols;
    }

    public function fitsWithin(int $trackCount): bool
    {
        if ($this->span instanceof Optional) {
            return true;
        }

        return $this->span <= $trackCount;
    }
}

----------[ Good ]----------

// The FIX for {@see GridBox}: every leaf gets a CONCRETE default — a box always has a column count,
// a span and a gap — and the optionality moves UP to the container field where the box itself may be
// absent (`LayoutBox|Optional $grid = new Optional()`). Present means valid; absent is one question,
// asked once, at the place that owns it.

final class LayoutBox extends Data
{
    public function __construct(
        public readonly int $columns = 1,
        public readonly int $span = 1,
        public readonly int $gap = 0,
    ) {}

    public function template(): string
    {
        return "grid-template-columns: repeat({$this->columns}, 1fr); gap: {$this->gap}px";
    }

    public function area(int $rows): int
    {
        return $rows * $this->columns;
    }
}

// The container that carries the absence the leaves used to scatter.

final class LayoutPanel extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly LayoutBox|Optional $grid = new Optional(),
    ) {}

    public function hasGrid(): bool
    {
        return ! $this->grid instanceof Optional;
    }
}
```

### data-collection-type

A `Data` property is TYPED as `DataCollection` — it should be `array` (or `Collection`) with `#[DataCollectionOf(X)]`; the `DataCollection` type emits malformed TypeScript and skips element-typed hydration

```php
----------[ Bad ]----------

final class RosterPage extends Data
{
    /**
     * @var DataCollection<int, Member>
     */
    public function __construct(
        public readonly string $club,
        public readonly int $season,
        public readonly string $coach,
        public readonly DataCollection $members,
    ) {}

    public function title(): string
    {
        return "{$this->club} {$this->season}";
    }

    public function underCoach(string $name): bool
    {
        return strcasecmp($this->coach, $name) === 0;
    }
}

----------[ Good ]----------

// The FIX for {@see RosterPage}: the roster is typed `array` and declares its element type with
// `#[DataCollectionOf(Member::class)]`. The element typing drives hydration and nested validation,
// and the generated TypeScript is a clean `Member[]` instead of `undefined<number, Member>`.

final class SquadPage extends Data
{
    /**
     * @param list<Member> $members
     */
    public function __construct(
        public readonly string $club,
        public readonly int $season,
        #[DataCollectionOf(Member::class)]
        public readonly array $members = [],
    ) {}

    public function squadSize(): int
    {
        return count($this->members);
    }
}
```

### data-method-hint-collision

`@method` tag that re-declares a real method (names the concrete factory, not the magic `from`/`collect`)

```php
----------[ Bad ]----------

// A discount coupon with public (non-promoted) properties and validation rules.
// Here the `@method` collides with the real static `rules()` — the collision sin
// is not specific to factories or to promoted-constructor classes.

final class CouponData extends Data
{
    public string $code;

    public int $percentOff;

    public static function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'percentOff' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}

----------[ Good ]----------

// The FIX for {@see InvoiceData}: the `@method` tag names the INVISIBLE magic `from()` — the one
// method the IDE cannot see — and says nothing about the concrete `fromOrder()` factory declared
// below it, so no hint re-declares a real method.

final class CreditNoteData extends Data
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $refundedCents,
        public readonly string $reference,
    ) {}

    public static function fromOrder(Order $order): self
    {
        return self::from([
            'orderId' => $order->id,
            'refundedCents' => $order->total_cents,
            'reference' => 'CN-' . $order->id,
        ]);
    }
}
```

### hook-missing-computed

A get-only property HOOK on a `Data` class lacks `#[Computed]` — Spatie reads the virtual property as a hydration INPUT, expects it in `::from()`, and crashes or silently drops it

```php
----------[ Bad ]----------

final class DockShell extends Data
{
    public array $docks { get => $this->dockSet->all(); }

    public function __construct(
        public readonly DockSet $dockSet,
        public readonly string $side,
    ) {}

    public function isEmpty(): bool
    {
        return $this->dockSet->all() === [];
    }

    public function onSide(string $side): bool
    {
        return $this->side === $side;
    }
}

----------[ Good ]----------

// The FIX for {@see DockShell}: the get-only hook is stamped `#[Computed]`, so Spatie treats `docks`
// as an OUTPUT-only value it derives after hydration — it is no longer expected in `::from()`.

final class ComputedDockShell extends Data
{
    #[Computed]
    public array $docks { get => $this->dockSet->all(); }

    public function __construct(
        public readonly DockSet $dockSet,
        public readonly string $side,
    ) {}

    public function facing(): string
    {
        return $this->side === 'port' ? 'starboard' : 'port';
    }
}
```

### manual-hydration-loop

Collections hydrated with `::from()` per item instead of `#[DataCollectionOf]` + `::collect()`

```php
----------[ Bad ]----------

public function map(array $rows): array
{
    $products = [];

    foreach ($rows as $row) {
        $products[] = ProductData::from($row);
    }

    return $products;
}

----------[ Good ]----------

// in Shop\Catalog\ProductImportMapper
// The FIX for {@see map()}: one pass, no loop — `::collect()` maps every row itself, and the
// element type is declared once on the receiving `#[DataCollectionOf(ProductData::class)]` slot.

public function collectAll(array $rows): mixed
{
    return ProductData::collect($rows);
}

// The slot `collectAll()` fills. The element type is declared ONCE, here, which is what lets
// `::collect()` map every row without a loop naming `ProductData` again at the call site.

final class ProductImportBatch extends Data
{
    /**
     * @param  array<int, ProductData>  $products
     */
    public function __construct(
        #[DataCollectionOf(ProductData::class)]
        public readonly array $products,
    ) {}
}
```

### manual-input-cast

A `Data` value-object property is hand-built at every construction site, instead of a `#[WithCast]` / `Castable` that owns the hydration once

```php
----------[ Bad ]----------

public function __construct(
    public readonly Money $price,
) {}

----------[ Good ]----------

// The FIX for {@see InboundOrderData}: the `simple → complex` mapping moves ONTO the property as a
// `#[WithCast(MoneyCast::class)]`, so every call site hands `::from()` the RAW cents (see
// {@see InboundControllers::castOrder()}) and the cast builds the `Money` in one place.

final class CastInboundOrderData extends Data
{
    public function __construct(
        #[WithCast(MoneyCast::class)]
        public readonly Money $price,
    ) {}

    public function priceLabel(): string
    {
        return $this->price->formatted();
    }
}

// The one home of the raw → `Money` mapping.

final class MoneyCast
{
    public function cast(mixed $value): Money
    {
        return new Money((int) $value, 'EUR');
    }
}
```

### nested-type-missing-typescript

A `#[TypeScript]` Data has a property typed as a nested `Data` class that itself lacks `#[TypeScript]` — the transformer emits it as `undefined`, a silent hole in the generated type (a nested enum is fine; the enum collector auto-generates it)

```php
----------[ Bad ]----------

#[TypeScript]
final class WirePanel extends Data
{
    /**
     * @param array<string, string> $tokens
     */
    public function __construct(
        public readonly PanelHeader|null $header = null,
        public readonly string $variant = 'plain',
        public readonly bool $bordered = true,
        public readonly array $tokens = [],
    ) {}

    public function classAttribute(): string
    {
        $classes = ['insp-panel', "insp-{$this->variant}"];

        if ($this->bordered) {
            $classes[] = 'insp-bordered';
        }

        return implode(' ', $classes);
    }

    public function styleVars(): string
    {
        $pairs = [];

        foreach ($this->tokens as $name => $value) {
            $pairs[] = "--{$name}: {$value}";
        }

        return implode('; ', $pairs);
    }
}

----------[ Good ]----------

// The FIX for {@see RosterBoard}: the nested Data the wire reaches is itself stamped `#[TypeScript]`,
// so the transformer generates a real `CrewSeat` type instead of `undefined` for every element.

#[TypeScript]
final class CrewBoard extends Data
{
    /**
     * @param list<CrewSeat> $crew
     */
    public function __construct(
        public readonly string $flight,
        #[DataCollectionOf(CrewSeat::class)]
        public readonly array $crew = [],
    ) {}

    public function headcount(): int
    {
        return count($this->crew);
    }
}

#[TypeScript]
final class CrewSeat extends Data
{
    public function __construct(
        public readonly string $deck,
        public readonly int $position,
        public readonly bool $assigned = false,
    ) {}
}
```

### new-data-object

`new <Data subclass>` instead of `::from()` / a `fromX()` factory

```php
----------[ Bad ]----------

public function legacyPresent(Order $order): OrderData
{
    return new OrderData(
        id: $order->id,
        status: $order->status,
        totalCents: $order->total_cents,
        lines: [],
    );
}

----------[ Good ]----------

// in Shop\Http\Presenters\OrderPresenter
// The FIX for {@see legacyPresent()}: the same order presented through the magic factory —
// `OrderData::from($order)` runs the casts, mappers and validation a raw `new` skips.

public function present(Order $order): OrderData
{
    return OrderData::from($order);
}

// Typed view of an order for the API.

final class OrderData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly OrderStatus $status,
        public readonly int $totalCents,
        #[DataCollectionOf(OrderLineData::class)]
        public readonly array $lines,
    ) {}
}
```

### non-final-data

Data class not `final` / props not `readonly` promoted

```php
----------[ Bad ]----------

// Stock level transfer object, left non-final.

class StockLevelData extends Data
{
    public function __construct(
        public readonly string $sku,
        public readonly int $onHand,
        public readonly int $reserved,
    ) {}

    public function available(): int
    {
        return max(0, $this->onHand - $this->reserved);
    }

    public function isLow(): bool
    {
        return $this->available() < 5;
    }

    public function status(): string
    {
        return match (true) {
            $this->available() === 0 => 'out-of-stock',
            $this->isLow() => 'low',
            default => 'ok',
        };
    }
}

----------[ Good ]----------

// The FIX for {@see OpenProfileData}: the same profile SEALED — `final`, with `readonly` promoted
// properties. A DTO is a leaf value, not a base to extend or mutate.

final class SealedProfileData extends Data
{
    public function __construct(
        public readonly string $displayName,
        public readonly string $locale,
        public readonly bool $marketingOptIn,
    ) {}

    public function label(): string
    {
        return $this->displayName . ' (' . $this->locale . ')';
    }
}
```

### null-to-optional-map

A producer hand-maps null→`new Optional` — `$x === null ? new Optional : Foo::from($x)` or `expr() ?? new Optional` — instead of one named factory (Spatie's `optional()` maps null→null, the opposite of what a `T|Optional` slot needs)

```php
----------[ Bad ]----------

public function position(): OptCoords|Optional
{
    return $this->rawPosition === null ? new Optional : OptCoords::from($this->rawPosition);
}

----------[ Good ]----------

// in Shop\Http\Pages\Hydration\MoveRequest
// The FIX: the null→`Optional` map lives in ONE named factory — `OptCoords::optionalOrMissing()`
// (the scaffolded `OptionalOrMissing` trait) — so no producer re-derives it with a ternary.

public function requestedPosition(): OptCoords|Optional
{
    return OptCoords::optionalOrMissing($this->rawPosition);
}

trait OptionalOrMissing
{
    public static function optionalOrMissing(mixed $payload): static|Optional
    {
        return $payload === null ? Optional::create() : static::from($payload);
    }
}
```

### nullable-wire-object

A nested object on a `#[TypeScript]` Data is typed `T | null` — it ships `null` on the wire where `T | Optional` would OMIT it (what the frontend's `x?.` reads for "absent")

```php
----------[ Bad ]----------

#[TypeScript]
final class WireNode extends Data
{
    public function __construct(
        public readonly int $percent,
        public readonly int $min = 0,
        public readonly int $max = 100,
        public readonly ?Band $band = null,
    ) {}

    public function clamped(): int
    {
        return max($this->min, min($this->max, $this->percent));
    }

    public function fraction(): float
    {
        $span = $this->max - $this->min;

        return $span === 0 ? 0.0 : ($this->clamped() - $this->min) / $span;
    }

    public function colour(): string
    {
        return match ($this->band) {
            Band::Danger => '#ef4444',
            Band::Warn => '#f59e0b',
            default => '#22c55e',
        };
    }
}

----------[ Good ]----------

// The FIX for {@see WireNode}: the genuinely-absent band is typed `Band|Optional = new Optional()`,
// not `?Band = null`. The wire now OMITS the key entirely when there is no band — which is what the
// frontend's `gauge.band?.` reads as "absent" — instead of shipping `"band": null`.

#[TypeScript]
final class WireDial extends Data
{
    public function __construct(
        public readonly int $percent,
        public readonly int $floor = 0,
        public readonly int $ceiling = 100,
        public readonly Band|Optional $band = new Optional(),
    ) {}

    public function needle(): int
    {
        return max($this->floor, min($this->ceiling, $this->percent));
    }

    public function ramp(): string
    {
        if ($this->band instanceof Optional) {
            return '#22c55e';
        }

        return $this->band === Band::Danger ? '#ef4444' : '#f59e0b';
    }
}
```

### prefer-optional-create

A raw `new Optional` is constructed in a runtime expression where Spatie's built-in `Optional::create()` factory reads clearer

```php
----------[ Bad ]----------

public function latest(): OptTag|Optional
{
    return $this->memo ?? new Optional;
}

----------[ Good ]----------

// The FIX: Spatie's own `Optional::create()` factory replaces the raw `new Optional` — a static
// call is legal here, so the factory is what belongs (only a parameter/property DEFAULT, where a
// call is illegal, keeps `new Optional`).

public function lastKnown(): OptTag|Optional
{
    if ($this->memo === null) {
        return Optional::create();
    }

    return $this->memo;
}
```
