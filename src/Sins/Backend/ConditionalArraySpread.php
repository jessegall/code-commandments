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
            description: 'An array built by spreading a conditional element — `...($x ? [\'k\' => $x] : [])` or `array_merge($base, $cond ? [...] : [])` — a ternary-and-empty-array trick that really just means "include this when the value is present."',
            rule: "Don't spread a `cond ? [...] : []` to conditionally include a key. Give the target a null-dropping variadic factory (`::of(mixed ...\$values)` that filters out nulls) and pass the value as a named arg — an absent one vanishes with no ternary.",
            suggestion: "Replace `[...\$base, ...(\$x !== null ? ['k' => \$x] : [])]` with a `::of(k: \$x, …)` factory that drops null-valued arguments.",
        );
    }
}
