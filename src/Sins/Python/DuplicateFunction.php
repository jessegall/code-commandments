<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Duplication;

final class DuplicateFunction extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'duplicate-python-function',
            skill: Duplication::class,
            description: 'Copy-pasted code — two+ Python functions or methods with an identical body, formatting, comments and docstrings aside',
            rule: 'Hoist a function body written twice into one shared function, and call it from both places.',
            suggestion: 'Move the body to one function in a module both callers import (or a method on the class that owns the data), and replace every copy with a call to it.',
        );
    }
}
