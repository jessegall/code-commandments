<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Detectors\Backend\GenericExceptionDetector;
use JesseGall\CodeCommandments\Detectors\Backend\InlineThrowDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Shipment;

/**
 * Formats a shipment's carrier — throwing inline as the receiver of a method call
 * (`(... ?? throw ...)->name()`), a control-flow branch buried mid-expression.
 */
final class TrackingFormatter
{
    #[Sinful(InlineThrowDetector::class)]
    #[Sinful(GenericExceptionDetector::class)]
    public function carrierName(Shipment $shipment): string
    {
        return ($shipment->carrier ?? throw new \RuntimeException('shipment has no carrier'))->displayName();
    }
}
