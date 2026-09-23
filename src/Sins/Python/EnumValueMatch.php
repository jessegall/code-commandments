<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Enums;

final class EnumValueMatch extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-enum-value-match',
            skill: Enums::class,
            description: '`match status.value: case "paid": …` at a call site — the enum\'s raw values matched again where the enum could answer',
            rule: 'Put a mapping over an enum\'s cases on the enum, matching its members; never match its raw `.value` at a call site.',
            suggestion: 'A method on the enum — `def badge(self) -> str: match self: case Status.PAID: …` — and `status.badge()` at the call site.',
        );
    }
}
