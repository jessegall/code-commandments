<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\QueryInLoop;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Shipment;

/**
 * Writes a tracking code onto each shipment that is missing one. Every turn of the loop saves one
 * row, so a backfill of ten thousand shipments is ten thousand writes while the method reads as
 * one piece of work.
 */
final class ShipmentBackfill
{
    /**
     * @param array<int, string> $codesByShipmentId
     */
    #[Sinful(QueryInLoop::class)]
    public function fill(array $codesByShipmentId): int
    {
        $written = 0;
        foreach ($codesByShipmentId as $shipmentId => $code) {
            $shipment = new Shipment();
            $shipment->id = $shipmentId;
            $shipment->tracking_code = $code;
            $shipment->save();
            $written++;
        }

        return $written;
    }
}
