<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\ComputedBooleanArgument;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Books the courier slot for a consignment.
 */
final class CourierBooking
{
    #[Sinful(ComputedBooleanArgument::class)]
    public function slot(Consignment $consignment): string
    {
        return DispatchWindow::pick($consignment->isExpress(), $consignment->lands());
    }
}
