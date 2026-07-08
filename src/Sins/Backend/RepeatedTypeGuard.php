<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\RepeatedCallHelper;

final class RepeatedTypeGuard extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'repeated-type-guard',
            skill: RepeatedCallHelper::class,
            description: "The SAME multi-`instanceof` type-narrowing guard (`\$x instanceof A && \$x->y instanceof B`) is written verbatim in ≥2 places — a check with no name, copied instead of named",
            rule: "Promote a recurring `instanceof` chain to a named predicate (`\$x->isNewOfNamedClass()`), so the intent has a name and the narrowing has ONE home.",
            suggestion: "Extract the repeated `instanceof` chain into a named boolean method and call THAT at each site.",
        );
    }
}
