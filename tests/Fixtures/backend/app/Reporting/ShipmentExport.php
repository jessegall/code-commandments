<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\QueryInLoop;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Models\Shipment;

/**
 * Writes shipments already loaded into rows for a spreadsheet, each shipment as its own attributes.
 */
final class ShipmentExport
{
    /**
     * @param  list<Shipment>  $shipments
     * @return list<array<string, mixed>>
     */
    #[Righteous(QueryInLoop::class)]
    public function rows(array $shipments): array
    {
        $rows = [];
        foreach ($shipments as $shipment) {
            $rows[] = $shipment->toArray();
        }

        return $rows;
    }
}
