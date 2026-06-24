<?php

namespace App\RunStatus;

use RuntimeException;

/**
 * Drives a run between states.
 *
 * SYMPTOM: with no status type to ask, this re-implements the whole transition
 * table as a hand-rolled ladder of literal-string compares. The legal moves
 * live here as data the rest of the app can't see, so the policy / presenter /
 * notifier each re-guess the same strings independently.
 */
final class RunStateMachine
{
    public function transition(WorkflowRun $run, string $next): WorkflowRun
    {
        $from = $run->status;

        $allowed = false;

        if ($from === 'queued' && ($next === 'running' || $next === 'cancelled')) {
            $allowed = true;
        }

        if ($from === 'running' && ($next === 'completed' || $next === 'failed' || $next === 'cancelled')) {
            $allowed = true;
        }

        if (! $allowed) {
            throw new RuntimeException("A run that is {$from} cannot move to {$next}.");
        }

        return $run->withStatus($next);
    }

    public function start(WorkflowRun $run): WorkflowRun
    {
        return $this->transition($run, 'running');
    }

    public function complete(WorkflowRun $run): WorkflowRun
    {
        return $this->transition($run, 'completed');
    }

    public function fail(WorkflowRun $run): WorkflowRun
    {
        return $this->transition($run, 'failed');
    }
}
