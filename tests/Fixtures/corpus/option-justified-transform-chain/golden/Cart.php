<?php

namespace App\OptionCorpus\OptionJustifiedTransformChain;

/** Minimal cart used by the call sites. */
final class Cart
{
    private float $total = 100.0;

    public function total(): float
    {
        return $this->total;
    }

    public function applyDiscount(int $percentOff): void
    {
        $this->total *= (1 - $percentOff / 100);
    }
}
