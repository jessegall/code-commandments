<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class DataMethodHintCollision extends Sin implements RequiresPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'data-method-hint-collision',
            skill: SpatieData::class,
            description: "`@method` tag that re-declares a real method (names the concrete factory, not the magic `from`/`collect`)",
            rule: "A `@method` hint must name the magic `from`/`collect`, never re-declare a real method (no IDE collision)."
        );
    }
}
