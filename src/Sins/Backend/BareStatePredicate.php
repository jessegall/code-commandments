<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\MethodMood;

final class BareStatePredicate extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'bare-state-predicate',
            skill: MethodMood::class,
            description: 'A `bool` about the object\'s own state named as a bare verb — `binds()`, `spins()` — where a question belongs',
            rule: 'A `bool` answering about the object itself wears a question: `isBound()`, `isSpinning()`, `hasParent()`, `awaitsAnswer()`. (A predicate that takes what it compares against — `contains(\$item)`, `matches(\$name)` — is already a sentence and stays as it is.)',
            suggestion: "make it a question: `is…`, `has…`, `can…`, `awaits…`",
        );
    }
}
