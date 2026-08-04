<?php

namespace Shop\Legacy;

use JesseGall\CodeCommandments\Sins\Backend\BloatedDocblock;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Builds the monthly sales report. It pulls orders, groups them by customer and
 * region, applies the tax tables, and renders a spreadsheet that finance imports
 * by hand every month.
 *
 * The grouping logic is shared with the dashboard widgets, so any change here
 * must be mirrored there until the two are unified.
 */
#[Sinful(BloatedDocblock::class)]
final class LegacyReportBuilder
{
    public function build(int $month): string
    {
        return "report-{$month}.xlsx";
    }
}

/**
 * Renders the monthly sales spreadsheet finance imports by hand.
 */
#[Fixed(BloatedDocblock::class)]
final class MonthlySalesReport
{
    public function __construct(private readonly SalesGrouping $grouping) {}

    public function render(int $month): string
    {
        return $this->grouping->for($month) . "-report.xlsx";
    }
}

/**
 * Groups a month's orders by customer and region — the shared decision the dashboard widgets ask for
 * too, so it has one home.
 */
final class SalesGrouping
{
    public function for(int $month): string
    {
        return "grouped-{$month}";
    }
}
