<?php

namespace Shop\Dispatch;

final class PickupSlot
{
    public function __construct(private readonly int $hour) {}

    public function startHour(): int
    {
        return $this->hour;
    }

    public function label(): string
    {
        return $this->hour . ':00';
    }
}
