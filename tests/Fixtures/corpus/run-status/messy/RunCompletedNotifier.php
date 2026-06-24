<?php

namespace App\RunStatus;

use Psr\Log\LoggerInterface;

/**
 * Emits an end-of-run notice once a run reaches a terminal state.
 *
 * SYMPTOM: the terminal-state check is inlined here for the FOURTH time, with
 * yet another drift — a typo'd 'faild' arm means a failed run never fires its
 * end-of-run notice. The notifier has no status type to ask `isTerminal()`, so
 * it copy-pastes the literal list and gets it subtly wrong.
 */
final class RunCompletedNotifier
{
    public function __construct(
        private readonly LoggerInterface $log,
    ) {}

    public function notify(WorkflowRun $run): void
    {
        $terminal = $run->status === 'completed'
            || $run->status === 'faild'
            || $run->status === 'cancelled';

        if (! $terminal) {
            return;
        }

        $this->log->info("Run {$run->id} finished: {$run->status}.", [
            'workflow_id' => $run->workflowId,
            'status' => $run->status,
        ]);
    }
}
