<?php

namespace App\FeatureEnvy\CartDiscount;

/**
 * Loyalty tiers a customer can hold, each carrying its own base discount rate.
 */
enum CustomerTier: string
{
    /** New or low-volume account; no loyalty discount. */
    case Bronze = 'bronze';

    /** Returning account with steady spend; a small standing discount. */
    case Silver = 'silver';

    /** High-value account; the richest standing discount. */
    case Gold = 'gold';

    public function discountPercent(): int
    {
        return match ($this) {
            CustomerTier::Bronze => 0,
            CustomerTier::Silver => 5,
            CustomerTier::Gold => 10,
        };
    }
}
