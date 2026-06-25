<?php

namespace App\RunStatus;

/**
 * Drives a run between states, refusing any move the status type forbids.
 */
final class RunStateMachine
{
    public function transition(WorkflowRun $run, RunStatus $next): WorkflowRun
    {
        if (! $run->status->canTransitionTo($next)) {
            throw IllegalTransitionException::between($run->status, $next);
        }

        return $run->withStatus($next);
    }

    public function start(WorkflowRun $run): WorkflowRun
    {
        return $this->transition($run, RunStatus::Running);
    }

    public function complete(WorkflowRun $run): WorkflowRun
    {
        return $this->transition($run, RunStatus::Completed);
    }

    public function fail(WorkflowRun $run): WorkflowRun
    {
        return $this->transition($run, RunStatus::Failed);
    }
}
