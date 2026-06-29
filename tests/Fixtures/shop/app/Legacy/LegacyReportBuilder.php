<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Detectors\Backend\BloatedDocblockDetector;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Builds the monthly sales report. It pulls orders, groups them by customer and
 * region, applies the tax tables, and renders a spreadsheet that finance imports
 * by hand every month.
 *
 * The grouping logic is shared with the dashboard widgets, so any change here
 * must be mirrored there until the two are unified.
 */
#[Sinful(BloatedDocblockDetector::class)]
final class LegacyReportBuilder
{
    public function build(int $month): string
    {
        return "report-{$month}.xlsx";
    }
}
