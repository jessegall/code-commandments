<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\DependencyDirection;

final class NamespaceDependency extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-namespace-dependency',
            skill: DependencyDirection::class,
            description: 'an import out of a declared layer into a package that layer did not declare it may use',
            rule: 'A declared layer may only import the packages it declared in its `mayUse` — down the stack, never back up or sideways.',
            suggestion: 'Move what both need down into the lower layer, pass it in from above, or invert it behind a protocol the lower layer owns — and if the declaration is what is wrong, say so rather than editing it quietly.',
        );
    }
}
