<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\MethodMood;

final class BareStatePredicate extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-bare-state-predicate',
            skill: MethodMood::class,
            description: 'a `bool` about the object\'s own state named as a claim — `Binds()`, `Spins` — where a question belongs',
            rule: 'Name a `bool` about the object itself as a question: `IsBound`, `IsSpinning`, `HasParent`, `CanRetry`.',
            suggestion: 'Rename it to a question — `Binds()` becomes `IsBound`, usually as a property.',
        );
    }
}
