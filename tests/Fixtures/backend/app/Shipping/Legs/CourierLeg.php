<?php

namespace Shop\Shipping\Legs;

use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Shipping\Consignment;
use Shop\Shipping\CourierGateway;
use Shop\Shipping\DeliveryWindow;
use Shop\Shipping\Zone;

/**
 * A leg a courier drives.
 */
final class CourierLeg extends Leg
{
    /**
     * The twin of {@see DepotLeg::__construct} — each promotes its own collaborator and hands the rest to the
     * parent once, which holds the shared initialisation; there is nothing left to share.
     */
    #[Righteous(DuplicateFunction::class)]
    public function __construct(
        Consignment $consignment,
        protected CourierGateway $courier,
        Zone $from,
        Zone $to,
        DeliveryWindow $window,
        int $stops,
    ) {
        parent::__construct($consignment, $from, $to, $window, $stops);
    }

    /**
     * The courier that drives the leg.
     */
    public function courier(): CourierGateway
    {
        return $this->courier;
    }
}
