<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class NullableWireObject extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'nullable-wire-object',
            skill: SpatieData::class,
            description: "A nested object on a `#[TypeScript]` Data is typed `T | null` — it ships `null` on the wire where `T | Optional` would OMIT it (what the frontend's `x?.` reads for \"absent\")",
            rule: "On a `#[TypeScript]` (frontend-bound) Data, type a genuinely-absent nested object `T | Optional = new Optional()`, not `T | null` — so the wire omits it rather than carrying a `null`.",
            suggestion: "`public readonly UiChrome|Optional \$chrome = new Optional();`, not `public readonly UiChrome|null \$chrome = null;`.",
        );
    }
}
