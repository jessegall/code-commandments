<?php

namespace Shop\Steps;

/**
 * How fast a run moves from one step to the next.
 */
enum StepPace: string
{
    case Calm = 'calm';

    case Brisk = 'brisk';

    public function pauseSeconds(): int
    {
        return match ($this) {
            self::Calm => 3,
            self::Brisk => 1,
        };
    }
}
