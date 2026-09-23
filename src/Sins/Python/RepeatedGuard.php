<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\RepeatedCallHelper;

final class RepeatedGuard extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-repeated-guard',
            skill: RepeatedCallHelper::class,
            description: 'the SAME compound `and` condition recurs in 2+ places — reordered or read through a local still counts — a question with no name',
            rule: 'Name a compound condition you write twice — a property or method on the type it asks about — and ask it by name at every site.',
            suggestion: 'Move the condition onto the type as `is_…` / `can_…` and replace every copy with the call.',
        );
    }
}
