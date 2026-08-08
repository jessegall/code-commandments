<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend\TypeScript;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\TypeScript\Absence;

final class FalselyOptionalField extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'falsely-optional-field',
            skill: Absence::class,
            description: "A field declared optional (`x?: T`, `T | null`) that is initialised where it is declared — it is never absent, and every `?.` and `??` downstream defends a case that cannot happen",
            rule: "Do not declare a field optional when it always has a value: drop the `?` and the `| null`, and the defences downstream go with them.",
            suggestion: "Declare it as its plain type — the initialiser already proves it is total.",
        );
    }
}
