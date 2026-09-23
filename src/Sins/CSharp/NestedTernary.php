<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Flow;

final class NestedTernary extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-nested-ternary',
            skill: Flow::class,
            description: 'a `?:` with another `?:` as one of its branches — several decisions packed into one expression',
            rule: 'Use one `?:` for one choice; for more, use a `switch` expression or early returns.',
            suggestion: 'Rewrite it as a `switch` expression (`grams switch { < 100 => "small", < 1000 => "medium", _ => "large" }`) or as `if` statements that return.',
        );
    }
}
