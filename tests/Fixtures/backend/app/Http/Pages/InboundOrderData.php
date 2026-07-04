<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualInputCast;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/**
 * `$price` is hand-built (`new Money(...)`) in the source of every `::from()` site (see
 * {@see InboundControllers}) — the `simple → complex` mapping copy-pasted per caller. A `#[WithCast]` /
 * `Castable` should own it once.
 */
final class InboundOrderData extends Data
{
    #[Sinful(ManualInputCast::class)]
    public function __construct(
        public readonly Money $price,
    ) {}

    public function isFree(): bool
    {
        return $this->price->cents === 0;
    }
}
