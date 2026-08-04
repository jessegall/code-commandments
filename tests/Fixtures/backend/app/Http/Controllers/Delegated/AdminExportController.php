<?php

namespace Shop\Http\Controllers\Delegated;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\RouteDelegatesToController;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A second door onto the export operation — it injects the real ExportController and forwards to it. A
 * controller wrapping a controller: the route should point at ExportController, or the work should move
 * to a shared service both call.
 */
final class AdminExportController
{
    public function __construct(
        private readonly ExportController $export,
        private readonly WorkflowExporter $exporter,
    ) {}

    #[Sinful(RouteDelegatesToController::class)]
    public function run(string $id): string
    {
        return $this->export->run($id);
    }

    /**
     * The FIX: the wrapper is gone. This action delegates INTO the domain — the same `WorkflowExporter`
     * the export controller calls — with the admin translation (`exportForAudit`) named on the service,
     * so there is no second HTTP door hanging off another controller.
     */
    #[Fixed(RouteDelegatesToController::class)]
    public function audit(string $id): string
    {
        return $this->exporter->exportForAudit($id);
    }

    public function history(array $ids): int
    {
        $count = 0;

        foreach ($ids as $id) {
            if ($id !== '') {
                $count++;
            }
        }

        return $count;
    }

    public function filename(string $prefix, string $id): string
    {
        return strtoupper($prefix) . '_' . str_pad($id, 6, '0', STR_PAD_LEFT) . '.csv';
    }
}
