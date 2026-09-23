<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Flow;

final class ConditionalStatement extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-conditional-statement',
            skill: Flow::class,
            description: 'a bare `a() if x else b()` statement — a conditional expression whose value nothing reads, so it chooses an action, not a value.',
            rule: 'Choose an action with `if`/`else`; a conditional expression chooses a value, so never write one whose value nothing reads.',
            suggestion: 'An `if x:` with each side as its own body — and no `else` at all when one side was `None`.',
        );
    }
}
