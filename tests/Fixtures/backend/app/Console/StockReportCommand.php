<?php

namespace Shop\Console;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\BoundaryDuplicatedOperation;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Reports\StockReport;

final class StockReportCommand extends Command
{
    protected $signature = 'reports:stock {warehouse}';

    #[Sinful(BoundaryDuplicatedOperation::class)]
    public function handle(StockReport $report): int
    {
        $rows = $report->rows((string) $this->argument('warehouse'));

        $this->line($report->toCsv($rows));

        return self::SUCCESS;
    }
}
