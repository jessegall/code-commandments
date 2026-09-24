<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\DependencyDirection;

final class NamespaceDependency extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-namespace-dependency',
            skill: DependencyDirection::class,
            description: 'a reference out of a declared layer into a namespace that layer did not declare it may use',
            rule: 'A declared layer may only use the namespaces it declared in its `mayUse` — down the stack, never back up or sideways.',
            suggestion: 'Move what both need down into the lower layer, pass it in from above, or invert it behind an interface the lower layer owns — and if the declaration is what is wrong, say so rather than editing it quietly.',
        );
    }
}
