<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\TypeHonesty;

final class PlaceholderFilledData extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'placeholder-filled-data',
            skill: TypeHonesty::class,
            description: "A required non-nullable `string` slot handed `''` — the type promises a value that is always there and the caller has none",
            rule: 'A required slot means the caller has the value. Filling it just to satisfy the type signature hides a missing value in a way no type check can catch.',
            suggestion: 'Fetch the real value, or split off a narrower type that only promises the fields you actually have.'
        );
    }
}
