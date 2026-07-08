<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieDataHydration;

final class HandKeyRemap extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'hand-key-remap',
            skill: SpatieDataHydration::class,
            description: "A `::from([...])` mechanically renames `\$src['snake_key']` → `camelKey` by hand, instead of a class-level `#[MapInputName]`",
            rule: "Map a snake_case boundary with one class-level `#[MapInputName(SnakeCaseMapper::class)]` + `::from(\$src)`, not a hand-written key translation.",
            suggestion: "`#[MapInputName(SnakeCaseMapper::class)]` on the class, then `SomeData::from(\$src)`."
        );
    }
}
