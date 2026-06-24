<?php

namespace App\RunStatus;

use RuntimeException;

/**
 * Thrown when a run is asked to move into a state its current state forbids.
 */
final class IllegalTransitionException extends RuntimeException
{
    public static function between(RunStatus $from, RunStatus $to): self
    {
        return new self("A run that is {$from->label()} cannot move to {$to->label()}.");
    }
}
