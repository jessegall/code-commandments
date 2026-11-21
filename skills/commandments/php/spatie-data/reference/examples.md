# Spatie Data — let the class build itself — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### data-method-hint-collision

`@method` tag that re-declares a real method (names the concrete factory, not the magic `from`/`collect`)

```php
----------[ Bad ]----------

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
public function collectAll(array $rows): mixed
{
    return ProductData::collect($rows);
}

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
public function present(Order $order): OrderData
{
    return OrderData::from($order);
}

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
