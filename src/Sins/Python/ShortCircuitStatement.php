<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Flow;

final class ShortCircuitStatement extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-short-circuit-statement',
            skill: Flow::class,
            description: 'a bare `a and b()` or `a or b()` statement — an `and`/`or` whose value nothing reads, so the operator is really acting as an `if`.',
            rule: 'Branch with an `if`; never run work off the right side of a bare `and`/`or` statement whose value nothing reads.',
            suggestion: 'Write the condition as an `if` and the right side as its body — `if not a: b()` for an `or`.',
        );
    }
}
