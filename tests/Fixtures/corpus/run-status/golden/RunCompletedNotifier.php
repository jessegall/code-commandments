<?php

namespace App\RunStatus;

use Psr\Log\LoggerInterface;

/**
 * Emits an end-of-run notice once, and only once a run reaches a terminal state.
 */
final class RunCompletedNotifier
{
    public function __construct(
        private readonly LoggerInterface $log,
    ) {}

    public function notify(WorkflowRun $run): void
    {
        if (! $run->status->isTerminal()) {
            return;
        }

        $this->log->info("Run {$run->id} finished: {$run->status->label()}.", [
            'workflow_id' => $run->workflowId,
            'status' => $run->status->value,
        ]);
    }
}
