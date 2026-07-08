<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class AllOptionalData extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'all-optional-data',
            skill: SpatieData::class,
            description: "Every field of a `Data` object is `T|Optional` — the type promises nothing is ever present; the absence belongs on the CONTAINER field where it's used",
            rule: "Don't make every field of a Data object `Optional` (the all-nullable smell in another skin). Almost always it's the WHOLE object that is present-or-absent — mark the enclosing field `Type|Optional` at its use site and give this object honest, concrete leaves, so if it exists it's a valid whole.",
            suggestion: "Give each leaf a concrete default (`int \$columns = 1`) and move the optionality up to where this type is used: `public readonly Grid|Optional \$grid = new Optional();`.",
        );
    }
}
