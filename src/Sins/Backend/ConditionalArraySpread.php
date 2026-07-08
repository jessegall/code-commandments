<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class ConditionalArraySpread extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'conditional-array-spread',
            skill: Absence::class,
            description: "An array is built by spreading a conditional element — `...(\$x ? ['k' => \$x] : [])` / `array_merge(\$base, \$cond ? [...] : [])` — the ternary-into-empty-array noise that hides 'include when present'",
            rule: "Don't spread a `cond ? [...] : []` to conditionally include a key. Give the target a null-dropping variadic factory (`::of(mixed ...\$values)` that filters out nulls) and pass the value as a named arg — an absent one vanishes with no ternary.",
            suggestion: "Replace `[...\$base, ...(\$x !== null ? ['k' => \$x] : [])]` with a `::of(k: \$x, …)` factory that drops null-valued arguments.",
        );
    }
}
