<?php

namespace Shop\Steps;

use RuntimeException;

/**
 * Thrown by a step to end the run where it stands; the runner catches it and stops.
 */
final class StopRun extends RuntimeException
{
    public static function here(): self
    {
        return new self('the run stops here');
    }
}
