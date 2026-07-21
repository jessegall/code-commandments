<?php

namespace Shop\Http\Controllers;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\BoundaryDuplicatedOperation;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Labels\LabelQueue;
use Shop\Labels\LabelRenderer;
use Shop\Labels\PrintLog;

/**
 * The third face of the same operation, reached over HTTP. Three boundaries, three copies of
 * render-queue-record, and nothing forces them to agree: the reprint audit gained a field once and
 * only two of the three learned about it.
 */
final class LabelPrintController
{
    #[Sinful(BoundaryDuplicatedOperation::class)]
    public function store(string $sku, LabelRenderer $renderer, LabelQueue $queue, PrintLog $log): string
    {
        $ticket = $queue->push($renderer->render($sku));

        $log->record($ticket);

        return $ticket;
    }
}
