<?php

namespace App\RunStatus;

/**
 * Decides what an actor may do to a run.
 *
 * SYMPTOM: "is this run still live?" and "did it fail?" are answered by literal
 * string compares against the status field. The terminal-state list is inlined
 * here AGAIN (and already drifted — it forgets 'cancelled', so a cancelled run
 * is wrongly treated as still cancellable).
 */
final class RunPolicy
{
    public function cancel(string $actorId, WorkflowRun $run): bool
    {
        $terminal = $run->status === 'completed' || $run->status === 'failed';

        return $actorId === $run->triggeredBy && ! $terminal;
    }

    public function retry(string $actorId, WorkflowRun $run): bool
    {
        return $actorId === $run->triggeredBy && $run->status === 'failed';
    }
}
