<?php

namespace App\FeatureEnvy\OrderStatus;

use DateTimeImmutable;

/**
 * A customer's order, deriving its own lifecycle status from its timestamps.
 */
final readonly class Order
{
    public function __construct(
        public string $id,
        public ?DateTimeImmutable $paidAt = null,
        public ?DateTimeImmutable $shippedAt = null,
        public ?DateTimeImmutable $deliveredAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
    ) {}

    public function status(): OrderStatus
    {
        $reached = [
            [$this->cancelledAt, OrderStatus::Cancelled],
            [$this->deliveredAt, OrderStatus::Delivered],
            [$this->shippedAt, OrderStatus::Shipped],
            [$this->paidAt, OrderStatus::Paid],
        ];

        foreach ($reached as [$at, $status]) {
            if ($at !== null) {
                return $status;
            }
        }

        return OrderStatus::Pending;
    }
}
