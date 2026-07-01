<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class ManualHydrationLoop extends Sin implements RequiresPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'manual-hydration-loop',
            skill: SpatieData::class,
            description: "Collections hydrated with `::from()` per item instead of `#[DataCollectionOf]` + `::collect()`",
            rule: "Hydrate a collection with `#[DataCollectionOf]` + `::collect()`, not a per-item `::from()` loop.",
            suggestion: "`#[DataCollectionOf(X::class)]` + `X::collect(\$rows)`."
        );
    }
}
