<?php

namespace Shop\Rentals;

/**
 * A piece of equipment lent to a customer until a due date.
 */
final class Rental
{
    public function __construct(
        public readonly int $id,
        public RentalStatus $status,
        public int $dueDay,
    ) {}

    public function extendBy(int $days): self
    {
        $this->dueDay += $days;

        return $this;
    }
}
