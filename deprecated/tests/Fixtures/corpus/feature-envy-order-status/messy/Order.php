<?php

namespace App\FeatureEnvy\OrderStatus;

use DateTimeImmutable;

/**
 * A customer's order, carrying the raw lifecycle timestamps but no behaviour.
 */
final class Order
{
    public function __construct(
        private string $id,
        private ?DateTimeImmutable $paidAt = null,
        private ?DateTimeImmutable $shippedAt = null,
        private ?DateTimeImmutable $deliveredAt = null,
        private ?DateTimeImmutable $cancelledAt = null,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function getShippedAt(): ?DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function getDeliveredAt(): ?DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }
}
