<?php

namespace App\FeatureEnvy\CartDiscount;

/**
 * Loyalty tiers a customer can hold.
 */
enum CustomerTier: string
{
    /** New or low-volume account; no loyalty discount. */
    case Bronze = 'bronze';

    /** Returning account with steady spend; a small standing discount. */
    case Silver = 'silver';

    /** High-value account; the richest standing discount. */
    case Gold = 'gold';
}
