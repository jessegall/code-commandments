<?php

namespace Shop\Pricing;

use JesseGall\CodeCommandments\Sins\Backend\NonCountingFor;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Climbs the discount ladder until a tier admits the quantity. The step is a CALL — the next
 * tier is fetched in the header, so where the loop ends is decided somewhere a reader has to
 * go looking for.
 */
final class TierLadder
{
    #[Sinful(NonCountingFor::class)]
    public function rateFor(int $quantity): float
    {
        for ($tier = $this->entryTier(); $tier !== null; $tier = $this->tierAbove($tier)) {
            if ($tier->admits($quantity)) {
                return $tier->rate();
            }
        }

        return 1.0;
    }

    private function entryTier(): ?object
    {
        return null;
    }

    private function tierAbove(object $tier): ?object
    {
        return null;
    }
}
