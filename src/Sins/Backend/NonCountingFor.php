<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\GuardClausesAndFlow;

final class NonCountingFor extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'non-counting-for',
            skill: GuardClausesAndFlow::class,
            description: 'A `for` loop that looks like it\'s counting, but its step actually assigns the next item instead of incrementing a counter.',
            rule: "Keep `for` for a counted loop, whose step advances a counter; walk with a `while`, or let the type hand out its own sequence.",
            suggestion: "A `while` over an explicit cursor — or better, an iterator on the type being walked, so the caller never holds the cursor at all.",
        );
    }
}
