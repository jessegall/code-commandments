<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\NonCountingFor;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Totals the distance of a shipment's legs, each leg linked to the next. The chain is walked
 * in the `for` step, so the header carries the traversal instead of a count.
 */
final class LegChain
{
    #[Sinful(NonCountingFor::class)]
    public function distance(?object $first): int
    {
        $metres = 0;

        for ($leg = $first; $leg !== null; $leg = $leg->next) {
            $metres += $leg->metres;
        }

        return $metres;
    }
}
