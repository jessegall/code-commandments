<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Flow;

final class RedundantElse extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'redundant-csharp-else',
            skill: Flow::class,
            description: 'An `else` after an `if` branch that already left — it ends in `return`, `throw`, `continue` or `break` — indenting the rest of the method for nothing',
            rule: 'Drop the `else` after a branch that returns, throws, continues or breaks — let the rest run at the method\'s own level.',
            suggestion: 'Delete the `else` and its braces and dedent its block; the exit above it already says the rest only runs when the condition was false.',
        );
    }
}
