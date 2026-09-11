<?php

namespace Shop\Orders;

use JesseGall\CodeCommandments\Sins\Backend\KeyedLookupEnvy;
use JesseGall\CodeCommandments\Testing\Righteous;
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

    /**
     * Keyed by an ENUM the ticket carries, not by the ticket: every ticket shipped the same way lands
     * on the same policy, so this is dispatch on a closed set — the strategy-over-a-scalar-field shape
     * tell-dont-ask exempts — not the ticket's own data fetched from outside it.
     */
    #[Righteous(KeyedLookupEnvy::class)]
    public function shippingWeight(OrderTicket $ticket): int
    {
        return $this->policies->forShipping($ticket->shipping)->weight;
    }

    public function floor(): int
    {
        return self::FLOOR;
    }
}
