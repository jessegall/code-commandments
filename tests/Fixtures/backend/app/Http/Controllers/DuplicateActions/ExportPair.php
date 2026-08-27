<?php

namespace Shop\Http\Controllers\DuplicateActions;

use Illuminate\Foundation\Http\FormRequest;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DuplicateRouteAction;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Two controllers, one operation: both forward to the same ReportBuilder::build — the same entry point
 * twice. The work already lives in the builder; collapse to a single action.
 */
class ReportExportRequest extends FormRequest {}

final class ReportBuilder
{
    public function build(ReportExportRequest $request): string
    {
        return 'report';
    }
}

final class ReportExportController
{
    public function __construct(private readonly ReportBuilder $builder) {}

    #[Sinful(DuplicateRouteAction::class)]
    public function build(ReportExportRequest $request): string
    {
        return $this->builder->build($request);
    }

    public function totalRows(array $rows): int
    {
        $total = 0;

        foreach ($rows as $row) {
            $total += count($row);
        }

        return $total;
    }

    public function schedule(string $cron): bool
    {
        return str_starts_with($cron, '0 ') && substr_count($cron, ' ') === 4;
    }

    public function retentionDays(string $plan): int
    {
        return match ($plan) {
            'free' => 7,
            'pro' => 90,
            default => 365,
        };
    }

    public function columnCount(array $selected, array $available): int
    {
        return count(array_intersect($selected, $available));
    }
}

final class AnalyticsExportController
{
    public function __construct(private readonly ReportBuilder $builder) {}

    #[Sinful(DuplicateRouteAction::class)]
    public function build(ReportExportRequest $request): string
    {
        return $this->builder->build($request);
    }

    public function window(int $days): string
    {
        return match (true) {
            $days <= 7 => 'week',
            $days <= 31 => 'month',
            default => 'quarter',
        };
    }
}

/**
 * The FIX: the second door onto the export is DELETED. `ReportExportController::build` is the one entry
 * point, `/analytics/export` now routes straight at it, and analytics keeps only the action that is
 * genuinely its own — one operation, one entry point.
 */
final class AnalyticsTrendController
{
    public function __construct(private readonly TrendBuilder $trends) {}

    #[Fixed(DuplicateRouteAction::class)]
    public function trend(ReportExportRequest $request): string
    {
        return $this->trends->plot($request);
    }
}

#[Fixed(DuplicateRouteAction::class)]
final class TrendBuilder
{
    public function plot(ReportExportRequest $request): string
    {
        return 'trend';
    }
}
