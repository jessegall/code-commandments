<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend\TypeScript;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\TypeScript\Absence;

final class DefendedCertainField extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'defended-certain-field',
            skill: Absence::class,
            description: 'An `?.` on a field the class declares as always present — a defence against a case the type says cannot happen, so the code doubts something the design already rules out.',
            rule: 'Reach for `?.` only where the type admits absence; on a field declared total, it is noise that makes the next reader wonder if it can be missing.',
            suggestion: "A plain `.` — the declaration already guarantees it.",
        );
    }
}
