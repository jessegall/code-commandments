<?php

namespace App\RunStatus;

/**
 * Turns a run's typed status into the badge label and colour the editor shows.
 */
final class RunStatusPresenter
{
    public function badge(WorkflowRun $run): RunStatusBadge
    {
        $status = $run->status;

        return new RunStatusBadge(
            label: $status->label(),
            colour: $status->colour(),
            terminal: $status->isTerminal(),
        );
    }
}
