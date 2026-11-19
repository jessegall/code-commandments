<?php

namespace Shop\Market;

use JesseGall\CodeCommandments\Sins\Backend\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Harvest\HarvestLedger;

final class StallPricing
{
    private HarvestLedger $ledger;

    private int $baseCents;

    #[Sinful(ConcreteCrossModuleDependency::class)]
    public function __construct(HarvestLedger $ledger, int $baseCents)
    {
        $this->ledger = $ledger;
        $this->baseCents = $baseCents;
    }

    public function centsPerKilo(string $crop): int
    {
        $picked = $this->ledger->kilosPicked($crop);
        if ($picked > 500) {
            return intdiv($this->baseCents * 8, 10);
        }

        return $this->baseCents;
    }
}
