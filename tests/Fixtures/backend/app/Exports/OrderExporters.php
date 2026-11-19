<?php

namespace Shop\Exports;

use JesseGall\CodeCommandments\Sins\Backend\LoneImplementerInterface;
use JesseGall\CodeCommandments\Sins\Backend\RefusedBequest;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Writes orders out for another system, now or on a schedule.
 */
#[Sinful(LoneImplementerInterface::class)]
interface OrderExporter
{
    public function export(array $orderIds): string;

    public function schedule(string $cron): void;
}

/**
 * A one-off CSV export that implements the scheduling it cannot do by refusing it.
 */
final class CsvOrderExport implements OrderExporter
{
    public function export(array $orderIds): string
    {
        return implode(',', $orderIds);
    }

    #[Sinful(RefusedBequest::class)]
    public function schedule(string $cron): void
    {
        throw ExportCannotBeScheduled::for($cron);
    }
}

/**
 * Scheduling an export that only runs once.
 */
final class ExportCannotBeScheduled extends \RuntimeException
{
    public static function for(string $cron): self
    {
        return new self("This export runs once and cannot follow {$cron}.");
    }
}
