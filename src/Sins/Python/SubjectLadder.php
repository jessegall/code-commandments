<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Flow;

final class SubjectLadder extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-subject-ladder',
            skill: Flow::class,
            description: 'An `if`/`elif` chain of four or more rungs that each test ONE subject for equality with a constant — a dispatch written as a ladder',
            rule: 'Dispatch on a value with an `Enum` that answers per case, a dict keyed by the value, or a `match` — never a ladder of `==` tests on one subject.',
            suggestion: 'Make the closed set an `Enum` and put the per-case answer on it, or look the answer up in a dict keyed by the value; a `match` fits a structural dispatch.',
        );
    }
}
