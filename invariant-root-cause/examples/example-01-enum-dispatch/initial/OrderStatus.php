<?php

declare(strict_types=1);

namespace Shop\Fulfilment;

enum OrderStatus: string
{
    case Pending   = 'pending';
    case Paid      = 'paid';
    case Shipped   = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * SMELL: closed-set dispatch with a `default => null`.
     *
     * Every real status has an SLA priority. The ONLY way this returns null is
     * the `default` arm — which only triggers for a case that was added to the
     * enum and never wired here (`Cancelled`). That null is an unhandled-case
     * BUG, not a genuine "no priority". Modelling it as `?int` pushes the
     * mistake onto every caller.
     */
    public function slaPriority(): ?int
    {
        return match ($this) {
            self::Pending   => 2,
            self::Paid      => 3,
            self::Shipped   => 4,
            self::Delivered => 1,
            default         => null,
        };
    }
}
