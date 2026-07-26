<?php

namespace Shop\Labels;

/**
 * The one home for "print a label" — the shared service every face should call.
 */
final class LabelPrinting
{
    public function __construct(
        private readonly LabelRenderer $renderer,
        private readonly LabelQueue $queue,
        private readonly PrintLog $log,
    ) {}

    public function print(string $sku): string
    {
        $jobId = $this->queue->push($this->renderer->render($sku));

        $this->log->record($jobId);

        return $jobId;
    }
}
