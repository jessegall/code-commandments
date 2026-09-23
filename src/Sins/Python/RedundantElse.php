<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Flow;

final class RedundantElse extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'redundant-python-else',
            skill: Flow::class,
            description: 'An `else:` after an `if` branch that already left — it ends in `return`, `raise`, `continue` or `break` — indenting the rest of the function for nothing',
            rule: 'Drop the `else:` after a branch that returns, raises, continues or breaks — let the rest run at the function\'s own level.',
            suggestion: 'Delete the `else:` line and dedent its block; the exit above it already says the rest only runs when the condition was false.',
        );
    }
}
