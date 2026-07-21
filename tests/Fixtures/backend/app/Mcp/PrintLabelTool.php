<?php

namespace Shop\Mcp;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\BoundaryDuplicatedOperation;
use JesseGall\CodeCommandments\Testing\Sinful;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\JsonSchema;
use Shop\Labels\LabelQueue;
use Shop\Labels\LabelRenderer;
use Shop\Labels\PrintLog;

/**
 * The MCP face of the label-printing operation. Everything above `handle()` is genuine protocol work
 * that belongs to this boundary alone — the schema, the description an agent reads, the shape of the
 * answer. What does NOT belong here is the operation itself, re-spelled a third time.
 */
final class PrintLabelTool extends Tool
{
    public function description(): string
    {
        return 'Render a label for a SKU, queue it for printing, and record the job for the reprint audit.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sku' => $schema->string()->description('The SKU whose label should be printed')->required(),
            'reason' => $schema->string()->description('Why the label is being printed'),
        ];
    }

    #[Sinful(BoundaryDuplicatedOperation::class)]
    public function handle(string $sku, LabelRenderer $renderer, LabelQueue $queue, PrintLog $log): string
    {
        $job = $queue->push($renderer->render($sku));

        $log->record($job);

        return $this->answer($job);
    }

    private function answer(string $job): string
    {
        return sprintf('Queued print job %s.', $job);
    }
}
