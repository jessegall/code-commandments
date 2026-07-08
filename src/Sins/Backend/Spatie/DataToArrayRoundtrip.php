<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieDataHydration;

final class DataToArrayRoundtrip extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'data-to-array-roundtrip',
            skill: SpatieDataHydration::class,
            description: "A `X::from(...)->toArray()` sits in a `::from` slot typed `X` that re-hydrates it — build → array → build",
            rule: "Don't `->toArray()` a `Data` into a slot that re-hydrates it; pass the object (or the source array) directly.",
            suggestion: "Drop the `->toArray()` — the nested-`Data` / `#[DataCollectionOf]` slot takes the object as-is."
        );
    }
}
