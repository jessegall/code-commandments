<?php

namespace Shop\Fulfillment\Bays;

use JesseGall\CodeCommandments\Sins\Backend\ReassignedParameter;
use JesseGall\CodeCommandments\Sins\Backend\UnreachableStatement;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The loading bay where parcels wait for their courier's van.
 */
final class CourierBay
{
    private int $waiting = 0;

    public function release(): int
    {
        $loaded = $this->waiting;
        $this->waiting = 0;

        return $loaded;
    }

    #[Sinful(ReassignedParameter::class)]
    public function receive(int $parcels, int $capacity): void
    {
        if ($parcels > $capacity) {
            $parcels = $capacity;
        }
        $this->waiting += $parcels;
    }

    /**
     * @param  list<int>  $weights
     */
    #[Sinful(UnreachableStatement::class)]
    public function heaviest(array $weights): int
    {
        $heaviest = 0;
        foreach ($weights as $weight) {
            if ($weight <= $heaviest) {
                continue;
                $heaviest = $weight;
            }
            $heaviest = $weight;
        }

        return $heaviest;
    }

    #[Righteous(ReassignedParameter::class)]
    public function topUp(int &$total): void
    {
        $total = $total + $this->waiting;
    }
}
