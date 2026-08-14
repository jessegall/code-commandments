<?php

namespace Shop\Dispatch;

/**
 * Passes the slot AND a piece of it to the same helper — the helper already holds the slot, so working
 * the hour out is its job. The redundant form of the sin: subject whole, then subject flattened.
 */
final class PickupWindow
{
    /**
     * @var array<int, string>
     */
    private array $booked = [];

    #[\JesseGall\CodeCommandments\Testing\Sinful(\JesseGall\CodeCommandments\Sins\Backend\DerivedArgument::class)]
    public function reserve(PickupSlot $slot): void
    {
        $this->hold($slot, $slot->startHour());
    }

    /**
     * @return array<int, string>
     */
    public function booked(): array
    {
        return $this->booked;
    }

    private function hold(PickupSlot $slot, int $hour): void
    {
        $this->booked[$hour] = $slot->label();
    }
}
