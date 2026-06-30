<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\KeyedLookupEnvy;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Ranks a ticket by reaching into a per-tier policy table keyed by the ticket's
 * own tier string. The rank is the ticket's business, derived from data it already
 * identifies — `$ticket->rank()` is where this computation belongs.
 */
final class PriorityCheck
{
    private const int FLOOR = 1;

    public function __construct(private readonly TierPolicies $policies) {}

    #[Sinful(KeyedLookupEnvy::class)]
    public function rankOf(OrderTicket $ticket): int
    {
        return $this->policies->for($ticket->tier)->weight;
    }

    public function floor(): int
    {
        return self::FLOOR;
    }
}
