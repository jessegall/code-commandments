<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Flow;

final class LoopWrappedInIf extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-loop-wrapped-in-if',
            skill: Flow::class,
            description: 'A `for` or `while` whose whole body is one `if` (no `else`) around real work — the iteration pushed a level deep behind a condition',
            rule: 'Invert a loop body wrapped in one `if` into a `continue` guard so the work sits at the loop\'s own level.',
            suggestion: 'Write `if not <condition>: continue` as the first line of the loop and dedent the body under it.',
        );
    }
}
