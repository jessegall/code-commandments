<?php

namespace Shop\Http\Pages\Hydration;

use Shop\Enums\ShippingMethod;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantEnumUnwrap;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * Scenario 3 — the enum is reached through a constructor-injected collaborator (`$this->shipment->method`)
 * and unwrapped at a single-slot Data. Injection + property-chain shape, distinct from scenarios 1 and 2.
 */
final class ShipmentLabel extends Data
{
    public function __construct(public readonly ShippingMethod $method) {}
}

final class Shipment
{
    public function __construct(public ShippingMethod $method) {}
}

final class LabelPrinter
{
    public function __construct(private readonly Shipment $shipment) {}

    #[Sinful(RedundantEnumUnwrap::class)]
    public function label(): ShipmentLabel
    {
        return ShipmentLabel::from(['method' => $this->shipment->method->value]);
    }
}
