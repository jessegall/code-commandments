<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class NonFinalData extends Sin implements RequiresPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'non-final-data',
            skill: SpatieData::class,
            description: "Data class not `final` / props not `readonly` promoted",
            rule: "Seal a Data class `final` with `readonly` promoted props — it's a leaf, not a base."
        );
    }
}
