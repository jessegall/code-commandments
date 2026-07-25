<?php

namespace Shop\Fulfillment;

/**
 * Books the courier slot for a consignment.
 */
final class CourierBooking
{
    public function slot(Consignment $consignment): string
    {
        return DispatchWindow::pick($consignment->isExpress(), $consignment->lands());
    }
}
