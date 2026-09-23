<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\RepeatedCallHelper;

final class RepeatedTypeGuard extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-repeated-type-guard',
            skill: RepeatedCallHelper::class,
            description: 'the same multi-`isinstance` narrowing (`isinstance(x, A) and isinstance(x.y, B)`) is written in 2+ places — a check on a shape that nobody has named.',
            rule: 'Name a type narrowing you write twice — a method, a property or a `TypeGuard` function — and ask for the shape by name.',
            suggestion: 'Move the chain into one named predicate (a `TypeGuard` where the caller needs the narrowed type) and call it at every site.',
        );
    }
}
