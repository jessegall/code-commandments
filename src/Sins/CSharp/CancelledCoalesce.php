<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Absence;

final class CancelledCoalesce extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-cancelled-coalesce',
            skill: Absence::class,
            description: 'a `??` fallback compared against the same value it falls back to — `(name ?? "") != ""` — so "missing" and "empty" end up in one branch without saying so',
            rule: 'Check for null directly (`name is not null`); don\'t fall back to a value only to compare against that same value.',
            suggestion: 'Write both checks out — `name is not null && name != ""` — or make the value non-nullable where it comes from, so only one check is left.',
        );
    }
}
