<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Flow;

final class DeepNesting extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'deep-python-nesting',
            skill: Flow::class,
            description: 'An `if`, loop or `match` opening a fourth level of choices inside one Python function — an arrow of conditions and loops',
            rule: 'Flatten with guard clauses and extraction — never bury a choice four deep inside a function.',
            suggestion: 'Guard the outer levels away (`return`/`continue` past what does not apply), let a comprehension do the inner iteration, or extract the inner block into a function named for what it decides.',
        );
    }
}
