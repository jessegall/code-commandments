<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Flow;

final class DeepNesting extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'deep-csharp-nesting',
            skill: Flow::class,
            description: 'An `if`, loop or `switch` opening a fourth level of choices inside one C# method — an arrow of conditions and loops',
            rule: 'Flatten with guard clauses and extraction — never bury a choice four deep inside a method.',
            suggestion: 'Guard the outer levels away (`return`/`continue` past what does not apply), let LINQ do the inner iteration, or extract the inner block into a method named for what it decides.',
        );
    }
}
