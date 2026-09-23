<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Absence;

final class InventedDefault extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-invented-default',
            skill: Absence::class,
            description: '`f(x or "")` — an empty string, `0` or `False` invented to fill an argument when the value is missing, a stand-in the callee cannot tell from real data',
            rule: 'Never fill an argument with an invented `""`, `0` or `False` on absence — handle the missing case, or make the value certain where it is born.',
            suggestion: 'Decide at the source: raise when the value must be there, or pass `None` on to a parameter that admits it. A real default (`or "EUR"`) is a choice, not an invention.',
        );
    }
}
