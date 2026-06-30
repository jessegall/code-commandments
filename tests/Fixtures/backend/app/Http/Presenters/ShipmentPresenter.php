<?php

namespace Shop\Http\Presenters;

use JesseGall\CodeCommandments\Sins\Backend\EnumValueMatch;

use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Shipment;

/**
 * Maps a shipment method to a UI icon by matching the enum's scalar at the call
 * site — homeless behaviour that belongs on ShippingMethod.
 */
final class ShipmentPresenter
{
    #[Sinful(EnumValueMatch::class)]
    public function icon(Shipment $shipment): string
    {
        $base = match ($shipment->method->value) {
            'standard' => 'truck',
            'express' => 'rocket',
            default => 'box',
        };

        return "icon-{$base}";
    }

    public function trackingUrl(Shipment $shipment): string
    {
        return "https://track.shop.test/{$shipment->tracking_code}";
    }

    public function isDelivered(Shipment $shipment): bool
    {
        return $shipment->tracking_code !== null && $shipment->order !== null;
    }
}
