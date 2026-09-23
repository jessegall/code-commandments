<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Enums;

final class StringMatchMirrorsEnum extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-string-match-mirrors-enum',
            skill: Enums::class,
            description: '`match raw: case "pending": …` whose cases are an existing enum\'s values — dispatching on loose strings the enum already seals',
            rule: 'Dispatch on the enum, not on loose strings that mirror its values; turn the string into the enum where it arrives.',
            suggestion: '`Status(raw)` at the boundary, then `match status: case Status.PENDING: …` — or a method on the enum.',
        );
    }
}
