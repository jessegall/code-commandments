<?php

namespace Shop\Shipping;

/**
 * The carrier no longer accepts the shop's contract, so nothing it sends will be booked.
 */
final class CarrierContractLapsed extends CarrierFailure
{
    public static function for(string $reference): self
    {
        return new self("The carrier holds no contract to book {$reference}.");
    }
}
