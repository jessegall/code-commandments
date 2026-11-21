# Enums with behaviour — seal the set, put the logic on the type — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### compare-self

A strict comparison bypasses an enum's verified comparison method.

```php
----------[ Bad ]----------

function hasDispatched(Dispatch $dispatch): bool
{
    return $dispatch !== Dispatch::Draft && $dispatch !== Dispatch::Pending;
}

----------[ Good ]----------

function alreadyDispatched(Dispatch $dispatch): bool
{
    return $dispatch->notEqualsAny(Dispatch::Draft, Dispatch::Pending);
}
```

### enum-value-match

A `match`/`switch` over an enum's `->value` at the call site — logic that belongs on the enum but lives elsewhere instead.

```php
----------[ Bad ]----------

public function colour(Product $product): string
{
    switch ($product->category->value) {
        case 'food':
            return 'green';
        case 'electronics':
            return 'blue';
        case 'clothing':
            return 'purple';
        default:
            return 'grey';
    }
}

----------[ Good ]----------

// in Shop\Catalog\StockBadge
public function colourViaEnum(Product $product): string
{
    return $product->category->badgeColour();
}

// in Shop\Enums\ProductCategory
public function badgeColour(): string
{
    return match ($this) {
        self::Food => 'green',
        self::Electronics => 'blue',
        self::Clothing => 'purple',
        self::Apparel => 'grey',
    };
}
```
