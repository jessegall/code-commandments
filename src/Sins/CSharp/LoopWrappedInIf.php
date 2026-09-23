<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Flow;

final class LoopWrappedInIf extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-loop-wrapped-in-if',
            skill: Flow::class,
            description: 'A `for`, `foreach` or `while` whose whole body is one `if` (no `else`) around real work — the iteration pushed a level deep behind a condition',
            rule: 'Invert a loop body wrapped in one `if` into a `continue` guard so the work sits at the loop\'s own level.',
            suggestion: 'Write `if (!<condition>) { continue; }` as the first statement of the loop and dedent the body under it — or, when the loop only filters, let `Where` say so.',
        );
    }
}
