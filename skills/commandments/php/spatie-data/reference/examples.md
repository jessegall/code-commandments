# Spatie Data — let the class build itself — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### data-method-hint-collision

`@method` tag that re-declares a real method (names the concrete factory, not the magic `from`/`collect`)

```php
----------[ Bad ]----------

// Typed invoice view, built from an order.

final class InvoiceData extends Data
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $totalCents,
        public readonly string $reference,
    ) {}

    // The `@method` tag re-declares THIS visible method — IDE "already defined".
    public static function fromOrder(Order $order): self
    {
        return self::from([
            'orderId' => $order->id,
            'totalCents' => $order->total_cents,
            'reference' => 'INV-' . $order->id,
        ]);
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
