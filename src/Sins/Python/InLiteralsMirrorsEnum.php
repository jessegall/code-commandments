<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Enums;

final class InLiteralsMirrorsEnum extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-in-literals-mirrors-enum',
            skill: Enums::class,
            description: '`x in ("pending", "late")` whose literals are an existing enum\'s values — a group of its members spelled as raw strings at the call site',
            rule: 'Test membership in an enum\'s group through the enum; never re-list its values as literals in an `in` test.',
            suggestion: 'A property on the enum naming the group — `Status(x).is_open` — or `x in (Status.PENDING, Status.LATE)` when the value is already the enum.',
        );
    }
}
