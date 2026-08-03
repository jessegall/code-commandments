<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\GuardClausesAndFlow;

final class ShortCircuitStatement extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'short-circuit-statement',
            skill: GuardClausesAndFlow::class,
            description: "a bare `\$a && \$b->do();` statement — a short-circuit whose result nothing reads, so the operator is an `if` in disguise",
            rule: "Branch with an `if`; never run work off the right side of a bare `&&`/`||` statement whose result nothing reads.",
        );
    }
}
