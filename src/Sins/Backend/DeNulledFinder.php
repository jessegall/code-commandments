<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class DeNulledFinder extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'de-nulled-finder',
            skill: Absence::class,
            description: 'A finder that returns `null` for both "missing" and "broken" instead of throwing — the kind of `?T` finder whose callers all end up de-nulling it.',
            rule: 'Decide absence where the value is found — if every caller ends up de-nulling a `?T` finder, make it return a definite type instead (throw, `Option`, or empty).',
            suggestion: "Add a resolve-or-throw `get()` beside `find()`, or return `Option<T>`."
        );
    }
}
