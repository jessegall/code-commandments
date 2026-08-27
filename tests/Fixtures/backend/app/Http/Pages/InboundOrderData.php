<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualInputCast;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\WithCast;
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

/**
 * The FIX for {@see InboundOrderData}: the `simple → complex` mapping moves ONTO the property as a
 * `#[WithCast(MoneyCast::class)]`, so every call site hands `::from()` the RAW cents (see
 * {@see InboundControllers::castOrder()}) and the cast builds the `Money` in one place.
 */
#[Fixed(ManualInputCast::class)]
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

/**
 * The one home of the raw → `Money` mapping.
 */
#[Fixed(ManualInputCast::class)]
final class MoneyCast
{
    public function cast(mixed $value): Money
    {
        return new Money((int) $value, 'EUR');
    }
}
