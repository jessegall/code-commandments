<?php

namespace Shop\Shipping\Tracking;

/**
 * The carrier's tracking API: where a parcel is and how long it still takes.
 */
final class CarrierClient
{
    /**
     * @var array<string, string>
     */
    private array $statuses = [];

    public function status(string $tracking): string
    {
        return $this->statuses[$tracking] ?? 'unknown';
    }

    public function hoursToDelivery(string $tracking): int
    {
        return strlen($this->status($tracking)) * 2;
    }
}
