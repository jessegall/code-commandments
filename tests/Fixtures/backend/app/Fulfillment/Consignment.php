<?php

namespace Shop\Fulfillment;

/**
 * One outbound consignment — the object that knows both how fast it must travel and when.
 */
final class Consignment
{
    public function __construct(
        private readonly string $service,
        private readonly int $dayOfWeek,
    ) {}

    public function isExpress(): bool
    {
        return $this->service === 'express';
    }

    public function lands(): bool
    {
        return $this->dayOfWeek > 5;
    }
}
