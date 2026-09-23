<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Flow;

final class SubjectLadder extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-subject-ladder',
            skill: Flow::class,
            description: 'An `if`/`else if` chain of four or more rungs that each compare the same subject with a constant — a dispatch written as a ladder.',
            rule: 'Dispatch on a value with a `switch` expression, or put the per-case behaviour on the type — never a ladder of `==` tests on one subject.',
            suggestion: 'Replace the ladder with a `switch` expression over the subject (the compiler checks it covers every case), or move each case\'s behaviour onto the type it belongs to.',
        );
    }
}
