<?php

namespace Shop\Http\Controllers\Delegated;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\RouteDelegatesToController;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The real, routed controllers — each the ONE home for its operation, delegating INTO a domain service.
 * That is the righteous shape: the RouteDelegatesToController detector must not flag them (their delegate
 * is a service, not another routed controller).
 */
#[Righteous(RouteDelegatesToController::class)]
final class ExportController
{
    public function __construct(private readonly WorkflowExporter $exporter) {}

    public function run(string $id): string
    {
        return $this->exporter->export($id);
    }
}

final class LabelController
{
    public function __construct(private readonly LabelPrinter $printer) {}

    public function print(string $sku): string
    {
        return $this->printer->print($sku);
    }
}

final class ReviewController
{
    public function __construct(private readonly ReviewPublisher $publisher) {}

    public function publish(int $reviewId): string
    {
        return $this->publisher->publish($reviewId);
    }
}

final class WorkflowExporter
{
    public function export(string $id): string
    {
        return $id;
    }
}

final class LabelPrinter
{
    public function print(string $sku): string
    {
        return $sku;
    }
}

final class ReviewPublisher
{
    public function publish(int $reviewId): string
    {
        return (string) $reviewId;
    }
}
