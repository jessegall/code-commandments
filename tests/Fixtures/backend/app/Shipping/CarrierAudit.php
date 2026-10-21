<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\QueryInLoop;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Order;

/**
 * Checks each carrier reference against the orders that claim it. The loop asks the database once
 * per reference, so a file of a thousand references is a thousand queries and the code reads as
 * though it were one. Below it, the FIX: one query before the loop, and the loop reads memory.
 */
final class CarrierAudit
{
    /**
     * @param array<int, string> $references
     *
     * @return array<int, string>
     */
    #[Sinful(QueryInLoop::class)]
    public function unclaimed(array $references): array
    {
        $missing = [];
        foreach ($references as $reference) {
            if (Order::where('carrier_reference', $reference)->count() === 0) {
                $missing[] = $reference;
            }
        }

        return $missing;
    }

    /**
     * @param array<int, string> $references
     *
     * @return array<int, string>
     */
    #[Fixed(QueryInLoop::class)]
    public function unclaimedReferences(array $references): array
    {
        $claimed = Order::whereIn('carrier_reference', $references)->pluck('carrier_reference')->all();

        return array_values(array_diff($references, $claimed));
    }
}
