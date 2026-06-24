<?php

namespace App\RunStatus;

/**
 * Decides what an actor may do to a run, gated on the run's typed status.
 */
final class RunPolicy
{
    public function cancel(string $actorId, WorkflowRun $run): bool
    {
        return $actorId === $run->triggeredBy && ! $run->status->isTerminal();
    }

    public function retry(string $actorId, WorkflowRun $run): bool
    {
        return $actorId === $run->triggeredBy && $run->status === RunStatus::Failed;
    }
}
