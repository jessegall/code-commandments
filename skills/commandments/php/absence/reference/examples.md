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
