<?php

namespace App\RunStatus;

/**
 * The lifecycle states a workflow run moves through, with the transitions and
 * terminality each state allows expressed as behaviour on the type itself.
 */
enum RunStatus: string
{
    /** Created but not yet started; queued to pick up an executor. */
    case Queued = 'queued';

    /** An executor is actively stepping through the run's nodes. */
    case Running = 'running';

    /** Every node finished cleanly; the terminal happy-path state. */
    case Completed = 'completed';

    /** A node raised and the run stopped; the terminal failure state. */
    case Failed = 'failed';

    /** Stopped on request before finishing; the terminal cancelled state. */
    case Cancelled = 'cancelled';

    public function canTransitionTo(RunStatus $next): bool
    {
        return match ($this) {
            RunStatus::Queued => match ($next) {
                RunStatus::Running, RunStatus::Cancelled => true,
                default => false,
            },
            RunStatus::Running => match ($next) {
                RunStatus::Completed, RunStatus::Failed, RunStatus::Cancelled => true,
                default => false,
            },
            RunStatus::Completed, RunStatus::Failed, RunStatus::Cancelled => false,
        };
    }

    /** A terminal state can never transition onward. */
    public function isTerminal(): bool
    {
        return match ($this) {
            RunStatus::Completed, RunStatus::Failed, RunStatus::Cancelled => true,
            RunStatus::Queued, RunStatus::Running => false,
        };
    }

    /** Human-facing label for the run's current state. */
    public function label(): string
    {
        return match ($this) {
            RunStatus::Queued => 'Queued',
            RunStatus::Running => 'Running',
            RunStatus::Completed => 'Completed',
            RunStatus::Failed => 'Failed',
            RunStatus::Cancelled => 'Cancelled',
        };
    }

    /** Badge colour the editor paints this state in. */
    public function colour(): string
    {
        return match ($this) {
            RunStatus::Queued => 'gray',
            RunStatus::Running => 'blue',
            RunStatus::Completed => 'green',
            RunStatus::Failed => 'red',
            RunStatus::Cancelled => 'amber',
        };
    }
}
