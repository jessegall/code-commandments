<?php

namespace Shop\Http\Controllers;

use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\BoundaryDuplicatedOperation;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Reports\StockReport;

final class StockReportController
{
    #[Sinful(BoundaryDuplicatedOperation::class)]
    public function download(StockReport $report, string $warehouse): string
    {
        return $report->toCsv($report->rows($warehouse));
    }

    #[Righteous(BoundaryDuplicatedOperation::class)]
    #[Sinful(ArrayReturnBag::class)]
    public function preview(StockReport $report, string $warehouse): array
    {
        return [
            'header' => $report->toCsv(['Warehouse', 'Metric', 'Quantity']),
            'rows' => $report->rows($warehouse),
        ];
    }
}
