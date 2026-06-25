<?php

namespace App\Orders;

/**
 * The lifecycle states an order moves through, from cart to fulfilment.
 */
enum OrderStatus: string
{
    /** Drafted but not yet submitted; the customer can still edit it. */
    case Pending = 'pending';

    /** Submitted and paid; awaiting the warehouse to pick and pack. */
    case Paid = 'paid';

    /** Handed to the carrier; in transit to the customer. */
    case Shipped = 'shipped';

    /** Delivered and closed; the terminal happy-path state. */
    case Completed = 'completed';

    /** Voided before fulfilment; the terminal unhappy-path state. */
    case Cancelled = 'cancelled';

    public function canTransitionTo(OrderStatus $next): bool
    {
        return match ($this) {
            OrderStatus::Pending => match ($next) {
                OrderStatus::Paid, OrderStatus::Cancelled => true,
                default => false,
            },
            OrderStatus::Paid => match ($next) {
                OrderStatus::Shipped, OrderStatus::Cancelled => true,
                default => false,
            },
            OrderStatus::Shipped => match ($next) {
                OrderStatus::Completed => true,
                default => false,
            },
            OrderStatus::Completed, OrderStatus::Cancelled => false,
        };
    }
}
