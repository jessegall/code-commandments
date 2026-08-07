<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class CancelledCoalesce extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'cancelled-coalesce',
            skill: Absence::class,
            description: "`??` cancelled by the comparison it sits in — `(\$x ?? '') !== ''`",
            rule: "Ask about absence directly (`\$x !== null`); never coalesce to a value only to compare against that same value.",
            suggestion: "Say both halves out loud — `\$x !== null && \$x !== ''` — or make the value non-nullable at its source so only one question is left.",
        );
    }
}
