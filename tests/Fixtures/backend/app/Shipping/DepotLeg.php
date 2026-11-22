<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\BareStatePredicate;
use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

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
     * Whether the leg stops at a depot before it leaves.
     */
    #[Sinful(BareStatePredicate::class)]
    public function stops(): bool
    {
        return $this->stops > 0;
    }
}
