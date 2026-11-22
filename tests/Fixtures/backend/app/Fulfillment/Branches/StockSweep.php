<?php

namespace Shop\Fulfillment\Branches;

use JesseGall\CodeCommandments\Sins\Backend\DuplicatedBranchTail;
use JesseGall\CodeCommandments\Sins\Backend\UnusedPrivateMember;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Sweeps the shelves at closing: empty bins are reordered, the rest are counted, and every bin is stamped.
 */
final class StockSweep
{
    /**
     * @var array<string, int>
     */
    #[Sinful(UnusedPrivateMember::class)]
    private array $stamped = [];

    /**
     * @param  array<string, int>  $bins
     */
    #[Sinful(DuplicatedBranchTail::class)]
    public function sweep(array $bins, int $at): int
    {
        $reorders = 0;
        foreach ($bins as $bin => $units) {
            if ($units === 0) {
                $reorders++;
                $this->stamped[$bin] = $at;
            } else {
                $this->stamped[$bin] = $at;
            }
        }

        return $reorders;
    }
}
