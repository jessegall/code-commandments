<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\GenericException;
use JesseGall\CodeCommandments\Sins\Backend\InlineThrow;
use JesseGall\CodeCommandments\Sins\Backend\MessageAtThrow;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Shipment;

/**
 * Formats a shipment's carrier — throwing inline as the receiver of a method call
 * (`(... ?? throw ...)->name()`), a control-flow branch buried mid-expression.
 */
final class TrackingFormatter
{
    #[Sinful(InlineThrow::class)]
    #[Sinful(GenericException::class)]
    #[Sinful(MessageAtThrow::class)]
    public function carrierName(Shipment $shipment): string
    {
        return ($shipment->carrier ?? throw new \RuntimeException('shipment has no carrier'))->displayName();
    }

    #[Righteous(GenericException::class)]
    public function carrierNameNamed(Shipment $shipment): string
    {
        return $shipment->carrier?->displayName()
            ?? throw CarrierMissing::for($shipment->id);
    }

    #[Righteous(InlineThrow::class)]
    public function carrierNameGuarded(Shipment $shipment): string
    {
        if ($shipment->carrier === null) {
            throw CarrierMissing::for($shipment->id);
        }

        return $shipment->carrier->displayName();
    }

    #[Righteous(MessageAtThrow::class)]
    public function carrierNameOrFail(Shipment $shipment): string
    {
        $carrier = $shipment->carrier ?? throw CarrierMissing::for($shipment->id);

        return $carrier->displayName();
    }
}
