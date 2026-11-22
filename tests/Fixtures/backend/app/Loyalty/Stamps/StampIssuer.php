<?php

namespace Shop\Loyalty\Stamps;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\QueryInLoop;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Stamps a member's card once for each order reference it is handed. The relation's create() inserts
 * one row a turn, so a member with forty orders to stamp is forty inserts.
 */
final class StampIssuer
{
    /**
     * @param list<string> $references
     */
    #[Sinful(QueryInLoop::class)]
    public function stamp(LoyaltyMember $member, array $references): void
    {
        foreach ($references as $reference) {
            $member->stampedOrders()->create(['reference' => $reference]);
        }
    }
}
