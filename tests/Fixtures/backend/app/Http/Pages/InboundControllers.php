<?php

namespace Shop\Http\Pages;

/**
 * The `::from()` hydration sites for the inbound data. Every site hand-builds the value object in the
 * source array — a `new VO(...)` inline, or a source assembled a statement earlier — so the mapping is
 * always manual and belongs in a cast. The righteous controller below feeds a clean value, sparing it.
 */
final class InboundControllers
{
    public function order(int $cents): InboundOrderData
    {
        return InboundOrderData::from(['price' => new Money($cents, 'EUR')]);
    }

    public function reorder(int $cents, string $code): InboundOrderData
    {
        return InboundOrderData::from(['price' => new Money($cents, $code)]);
    }

    /**
     * The call site of the FIXED data: the RAW cents go straight in — the `#[WithCast(MoneyCast::class)]`
     * on the property owns the mapping, so nothing is hand-built here.
     */
    public function castOrder(int $cents): CastInboundOrderData
    {
        return CastInboundOrderData::from(['price' => $cents]);
    }

    public function place(float $lat, float $lng): InboundPlaceData
    {
        $source = ['spot' => new GeoPoint($lat, $lng), 'radius' => 50];

        return InboundPlaceData::from($source);
    }

    public function booking(string $start, string $end): InboundBookingData
    {
        return InboundBookingData::from(['span' => new DateRange($start, $end)]);
    }
}

/**
 * RIGHTEOUS: a value object slot fed a value that arrives ready (a passed-through argument, not built
 * here). The mapping isn't done at the call site, so a cast isn't forced — the detector must NOT flag it.
 */
#[\JesseGall\CodeCommandments\Testing\Righteous(\JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualInputCast::class)]
final class CleanInboundData extends \Spatie\LaravelData\Data
{
    public function __construct(
        public readonly Money $price,
    ) {}
}

final class CleanInboundController
{
    public function make(Money $ready): CleanInboundData
    {
        return CleanInboundData::from(['price' => $ready]);
    }
}
