<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Absence;

final class NullForgiven extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-null-forgiven',
            skill: Absence::class,
            description: 'The null-forgiving `!` on a value declared nullable — the compiler told the caller it may be null, and `!` silences it instead of deciding',
            rule: 'Never silence a nullable warning with `!` — decide the missing case where the value is created, or handle it here.',
            suggestion: 'Make the value non-nullable at its source, throw a named exception where it must exist, handle the null branch, or narrow it honestly (`OfType<T>()`, a pattern, `TryGetValue`).',
        );
    }
}
