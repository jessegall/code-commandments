<?php

namespace Shop\Shipping\Legs;

use Shop\Shipping\Consignment;
use Shop\Shipping\DeliveryWindow;
use Shop\Shipping\Zone;

/**
 * One stretch of a consignment's journey, between two zones within a delivery window.
 */
abstract class Leg
{
    public function __construct(
        protected Consignment $consignment,
        protected Zone $from,
        protected Zone $to,
        protected DeliveryWindow $window,
        protected int $stops,
    ) {
    }

    /**
     * Whether the leg stays within one zone.
     */
    public function isLocal(): bool
    {
        return $this->from === $this->to;
    }
}
