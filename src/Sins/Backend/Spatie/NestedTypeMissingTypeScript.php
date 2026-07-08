<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class NestedTypeMissingTypeScript extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'nested-type-missing-typescript',
            skill: SpatieData::class,
            description: "A `#[TypeScript]` Data has a property typed as a nested `Data` class that itself lacks `#[TypeScript]` — the transformer emits it as `undefined`, a silent hole in the generated type (a nested enum is fine; the enum collector auto-generates it)",
            rule: "Every nested `Data` reachable on the wire from a `#[TypeScript]` class must ALSO be `#[TypeScript]` (or the property must declare its shape with `#[LiteralTypeScriptType]`), or it generates as `undefined`. Enums need no tag — they auto-generate.",
            suggestion: "Add `#[TypeScript]` to the nested `Data` class the property points at.",
        );
    }
}
