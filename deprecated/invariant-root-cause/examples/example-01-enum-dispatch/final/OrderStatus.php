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
     * Total dispatch: every case is handled and the `default` arm is gone, so
     * the return is a plain `int` — never absent. Adding a new enum case now
     * fails loud at compile time with an `UnhandledMatchError` instead of
     * silently producing `null`. The invariant ("every status has a priority")
     * is enforced by the language.
     */
    public function slaPriority(): int
    {
        return match ($this) {
            self::Pending   => 2,
            self::Paid      => 3,
            self::Shipped   => 4,
            self::Delivered => 1,
            self::Cancelled => 5,
        };
    }
}
