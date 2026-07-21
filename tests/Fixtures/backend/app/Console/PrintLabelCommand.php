<?php

namespace Shop\Console;

use Illuminate\Console\Command;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\BoundaryDuplicatedOperation;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Labels\LabelQueue;
use Shop\Labels\LabelRenderer;
use Shop\Labels\PrintLog;

/**
 * The console face of "print a label": render, queue, record. The MCP tool spells the same three
 * steps for itself, so a change to the operation has to be made twice and only ever gets made once.
 */
final class PrintLabelCommand extends Command
{
    protected $signature = 'labels:print {sku} {--copies=1}';

    protected $description = 'Render a label for a SKU and queue it for printing.';

    #[Sinful(BoundaryDuplicatedOperation::class)]
    public function handle(LabelRenderer $renderer, LabelQueue $queue, PrintLog $log): int
    {
        $copies = max(1, (int) $this->option('copies'));

        for ($printed = 0; $printed < $copies; $printed++) {
            $jobId = $queue->push($renderer->render((string) $this->argument('sku')));

            $log->record($jobId);
        }

        return 0;
    }
}
