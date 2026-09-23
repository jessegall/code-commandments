<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\MethodMood;

final class BareStatePredicate extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-bare-state-predicate',
            skill: MethodMood::class,
            description: 'a `bool` about the object\'s own state named as a bare verb — `binds()`, `spins` — where a question belongs',
            rule: 'Name a `bool` about the object itself as a question: `is_bound()`, `has_parent()`, `can_retry()`.',
            suggestion: 'Make it a question: `is_…`, `has_…`, `can_…`, `awaits_…`.',
        );
    }
}
