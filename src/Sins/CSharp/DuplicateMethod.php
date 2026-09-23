<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Duplication;

final class DuplicateMethod extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'duplicate-csharp-method',
            skill: Duplication::class,
            description: 'Copy-pasted code — two+ C# methods, accessors or local functions with an identical body, formatting, comments and attributes aside',
            rule: 'Hoist a method body written twice into one shared method, and call it from both places.',
            suggestion: 'Move the body to one method on the type that owns the data (or an extension or static helper both callers reference), and replace every copy with a call to it.',
        );
    }
}
