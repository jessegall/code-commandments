<?php

namespace Shop\Dispatch;

/**
 * One layer of a pallet: its reference, its weight and the cartons stacked on it.
 */
final readonly class PalletLayer
{
    /**
     * @param  list<string>  $cartons
     */
    public function __construct(public string $reference, public int $grams, public array $cartons) {}
}
