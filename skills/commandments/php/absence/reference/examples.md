# Absence — model "might not be there" honestly — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### blank-string-on-the-wire

A string sent over the wire that the frontend reads as absent when it is blank.

```php
----------[ Bad ]----------

final class VoucherPanelData extends Data
{
    /**
     * @param  array<int, string>  $rejectedCodes  every code the till turned away this basket
     */
    public function __construct(
        public readonly string $appliedCode,
        public readonly string $discountLabel,
        public readonly array $rejectedCodes = [],
    ) {}

    public function rejections(): int
    {
        return count($this->rejectedCodes);
    }

    public function turnedAway(string $code): bool
    {
        return in_array($code, $this->rejectedCodes, true);
    }
}

----------[ Good ]----------

final readonly class StockFeed
{
    public function __construct(
        public string $channel,
        public ?string $socket = null,
        public int $pollMs = 2000,
    ) {}

    public function isSocketed(): bool
    {
        return $this->socket !== null;
    }
}
```

### erased-null-object

A blank-rendering Null Object written into a `string` slot — coerced back to `''`

```php
----------[ Bad ]----------

public static function of(string $consignment, string $complaint = new BlankText): self
{
    return new self($consignment, $complaint);
}

----------[ Good ]----------

public static function noted(string $consignment, ?string $complaint = null): self
{
    return new self($consignment, $complaint);
}
```

### false-for-nothing

A return typed `X|false`: `false` stands in for "nothing" or "failed", and every caller must test for it.

```php
----------[ Bad ]----------

public function positionOf(string $sku): int|false
{
    return array_search($sku, $this->skus, true);
}

----------[ Good ]----------

public function slotOf(string $sku): int
{
    $position = array_search($sku, $this->skus, true);

    return is_int($position) ? $position : throw SkuNotIndexed::for($sku);
}
```

### option-as-nullable

`Option<T>` used as if it were nullable — `?Option`, `Option | null`, `unwrapOr(null)`

```php
----------[ Bad ]----------

public function locate(string $email): ?Option
{
    return Option::none();
}

----------[ Good ]----------

public function locateHonestly(string $email): Option
{
    return Option::fromTruthy($email);
}
```
