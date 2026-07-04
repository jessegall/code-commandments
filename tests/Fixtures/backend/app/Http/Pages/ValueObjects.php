<?php

namespace Shop\Http\Pages;

/**
 * Plain value objects (NOT `Data`, not enums) — the domain types a page carries. Their serialized wire
 * shape, when it must differ from the PHP type, belongs to a `#[WithTransformer]`, never a hand-rolled
 * getter that flattens them into an array. Righteous on their own; the sin is flattening them by hand.
 */
final class Money
{
    public function __construct(
        public readonly int $cents,
        public readonly string $code,
    ) {}

    public function formatted(): string
    {
        return $this->cents . ' ' . $this->code;
    }
}

final class GeoPoint
{
    public function __construct(
        public readonly float $lat,
        public readonly float $lng,
    ) {}

    public function label(): string
    {
        return $this->lat . ',' . $this->lng;
    }
}

final class DateRange
{
    public function __construct(
        public readonly string $start,
        public readonly string $end,
    ) {}
}
