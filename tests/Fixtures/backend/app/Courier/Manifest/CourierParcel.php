<?php

namespace Shop\Courier\Manifest;

/**
 * A parcel as the courier's manifest lists it: the courier knows nothing of the shop's own waybills.
 */
final class CourierParcel
{
    public function __construct(
        public readonly string $reference,
        public readonly int $grams,
        public readonly bool $twoPersonLift,
    ) {}

    public function line(): string
    {
        return $this->reference . ';' . $this->grams . ($this->twoPersonLift ? ';2P' : '');
    }
}
