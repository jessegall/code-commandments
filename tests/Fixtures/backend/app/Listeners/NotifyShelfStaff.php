<?php

namespace Shop\Listeners;

use Shop\Events\LoyaltyCardIssued;
use Shop\Events\ShelfRestocked;

/**
 * Tells the floor staff about a shelf or a card.
 */
final class NotifyShelfStaff
{
    public function handle(ShelfRestocked|LoyaltyCardIssued $event): void {}
}
