<?php

namespace Shop\Shipping\Legs;

use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Shipping\Consignment;
use Shop\Shipping\DeliveryWindow;
use Shop\Shipping\DepotChooser;
use Shop\Shipping\Zone;

/**
 * A leg that ends at a depot.
 */
final class DepotLeg extends Leg
{
    /**
     * The twin of {@see CourierLeg::__construct}.
     */
    #[Righteous(DuplicateFunction::class)]
    public function __construct(
        Consignment $consignment,
        protected DepotChooser $depots,
        Zone $from,
        Zone $to,
        DeliveryWindow $window,
        int $stops,
    ) {
        parent::__construct($consignment, $from, $to, $window, $stops);
    }

    /**
     * Whether the leg needs a depot picked before it leaves.
     */
    public function needsDepot(): bool
    {
        return $this->stops > 0;
    }
}
