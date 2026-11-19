<?php

namespace Shop\Rentals;

use JesseGall\CodeCommandments\Sins\Backend\EnumCaseOrChain;
use JesseGall\CodeCommandments\Sins\Backend\GuardBesideTransition;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The counter where customers extend what they rented, and where the week's late fees are totted up.
 */
final class RentalCounter
{
    #[Sinful(GuardBesideTransition::class)]
    #[Sinful(EnumCaseOrChain::class)]
    public function extend(Rental $rental, int $days): Rental
    {
        if ($rental->status === RentalStatus::Returned || $rental->status === RentalStatus::Lost) {
            throw RentalClosed::forRental($rental->id);
        }

        return $rental->extendBy($days);
    }

    #[Righteous(GuardBesideTransition::class)]
    public function lateDays(Rental $rental, int $today): int
    {
        if ($rental->status !== RentalStatus::Out) {
            throw RentalClosed::forRental($rental->id);
        }

        return max(0, $today - $rental->dueDay);
    }
}
