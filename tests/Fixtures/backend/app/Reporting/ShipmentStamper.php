<?php

namespace Shop\Reporting;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\QueryInLoop;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Models\Shipment;

/**
 * Stamps each code of a batch with the depot's first shipment, loaded once when the first code needs it.
 */
final class ShipmentStamper
{
    /**
     * @param  list<string>  $codes
     * @return list<string>
     */
    #[Righteous(QueryInLoop::class)]
    public function stamped(array $codes): array
    {
        $first = null;
        $stamped = [];
        foreach ($codes as $code) {
            $first ??= Shipment::query()->first();
            $stamped[] = $code . '-' . ($first?->getKey() ?? 'none');
        }

        return $stamped;
    }
}
