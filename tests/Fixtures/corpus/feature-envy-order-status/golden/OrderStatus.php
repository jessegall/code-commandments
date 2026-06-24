<?php

namespace App\FeatureEnvy\OrderStatus;

/**
 * The lifecycle state of an order, owning its own presentation.
 */
enum OrderStatus: string
{
    /** Submitted but not yet paid; awaiting payment capture. */
    case Pending = 'pending';

    /** Payment captured; awaiting the warehouse to pick and pack. */
    case Paid = 'paid';

    /** Handed to the carrier; in transit to the customer. */
    case Shipped = 'shipped';

    /** Received by the customer; the terminal happy-path state. */
    case Delivered = 'delivered';

    /** Voided before fulfilment; the terminal unhappy-path state. */
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            OrderStatus::Pending => 'Pending',
            OrderStatus::Paid => 'Paid',
            OrderStatus::Shipped => 'Shipped',
            OrderStatus::Delivered => 'Delivered',
            OrderStatus::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            OrderStatus::Pending => 'gray',
            OrderStatus::Paid => 'amber',
            OrderStatus::Shipped => 'blue',
            OrderStatus::Delivered => 'green',
            OrderStatus::Cancelled => 'red',
        };
    }

    public function isOpen(): bool
    {
        return match ($this) {
            OrderStatus::Pending, OrderStatus::Paid, OrderStatus::Shipped => true,
            OrderStatus::Delivered, OrderStatus::Cancelled => false,
        };
    }
}
