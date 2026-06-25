<?php

namespace App\RunStatus;

use Illuminate\Support\Fluent;

/**
 * Turns a run's status into the badge label + colour the editor shows.
 *
 * SYMPTOM: two more match ladders over the same status string — one for the
 * label, one for the colour — re-deriving presentation knowledge that should
 * hang off the status itself. Note the typo arm 'complete' (missing the trailing
 * 'd'): a completed run silently falls through to the grey 'Unknown' default.
 */
final class RunStatusPresenter
{
    public function badge(WorkflowRun $run): Fluent
    {
        $status = $run->status;

        $label = match ($status) {
            'queued' => 'Queued',
            'running' => 'Running',
            'complete' => 'Completed',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => 'Unknown',
        };

        $colour = match ($status) {
            'queued' => 'gray',
            'running' => 'blue',
            'completed' => 'green',
            'failed' => 'red',
            'cancelled' => 'amber',
            default => 'gray',
        };

        $terminal = $status === 'completed' || $status === 'failed' || $status === 'cancelled';

        return new Fluent([
            'label' => $label,
            'colour' => $colour,
            'terminal' => $terminal,
        ]);
    }
}
