<?php

namespace Shop\Fulfillment;

/**
 * The warehouse wall board — the same window rule, re-derived a second time.
 */
final class DispatchBoard
{
    public function column(Consignment $consignment): string
    {
        $window = DispatchWindow::pick($consignment->isExpress(), $consignment->lands());

        return strtoupper($window);
    }
}
