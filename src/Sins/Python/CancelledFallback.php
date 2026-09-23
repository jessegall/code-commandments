<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Absence;

final class CancelledFallback extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-cancelled-fallback',
            skill: Absence::class,
            description: '`(x or "") != ""` — a value defaulted to a blank only to be compared against that same blank, so absent and empty take one branch unnamed',
            rule: 'Ask about absence directly (`x is not None`); never default a value only to compare it against that same default.',
            suggestion: 'Say both halves out loud — `x is not None and x != ""` — or make the value non-optional where it is born so only one question is left.',
        );
    }
}
