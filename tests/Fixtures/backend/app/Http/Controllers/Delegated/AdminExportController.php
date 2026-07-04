<?php

namespace Shop\Http\Controllers\Delegated;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\RouteDelegatesToController;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A second door onto the export operation — it injects the real ExportController and forwards to it. A
 * controller wrapping a controller: the route should point at ExportController, or the work should move
 * to a shared service both call.
 */
final class AdminExportController
{
    public function __construct(private readonly ExportController $export) {}

    #[Sinful(RouteDelegatesToController::class)]
    public function run(string $id): string
    {
        return $this->export->run($id);
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
