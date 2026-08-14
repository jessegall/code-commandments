<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\DerivedArgument;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The warehouse wall board — the same window rule, re-derived a second time.
 */
final class DispatchBoard
{
    #[Sinful(DerivedArgument::class)]
    public function column(Consignment $consignment): string
    {
        $window = DispatchWindow::pick($consignment->isExpress(), $consignment->lands());

        return strtoupper($window);
    }
}
