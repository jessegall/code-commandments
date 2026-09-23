<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\DependencyDirection;

final class NamespaceCycle extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-namespace-cycle',
            skill: DependencyDirection::class,
            description: 'two of the project\'s packages import each other — a cycle that makes them one package split under two names.',
            rule: 'Keep imports between the project\'s packages pointing one way; two packages that import each other are a cycle.',
            suggestion: 'Move what both need into the lower package, pass it in from above, or invert it behind a protocol the lower one owns.',
        );
    }
}
