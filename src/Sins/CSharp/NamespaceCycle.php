<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\DependencyDirection;

final class NamespaceCycle extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-namespace-cycle',
            skill: DependencyDirection::class,
            description: 'two of the project\'s namespaces that each use the other — a cycle that makes them one namespace split under two names',
            rule: 'Keep references between the project\'s namespaces pointing one way; two namespaces that use each other are a cycle.',
            suggestion: 'Cut the thinner direction: move what both need into the lower namespace, pass it in from above, or invert it behind an interface the lower one owns.',
        );
    }
}
