<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Absence;

final class DeNulledFinder extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-de-nulled-finder',
            skill: Absence::class,
            description: 'a finder returning a nullable object whose every caller asserts it is there — `Find(id)!`, `Find(id) ?? throw …` — a miss the finder should have refused itself',
            rule: 'Decide absence where the value is found — if every caller treats a `T?` finder\'s miss as impossible, give it a resolve-or-throw form instead of re-asserting at each call site.',
            suggestion: 'Add a resolve-or-throw `Get(id)` beside `Find(id)` and call it where the callers de-null, or make the finder throw on a miss.',
        );
    }
}
