<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\SpatieData;

final class ManualHydrationLoop extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'manual-hydration-loop',
            skill: SpatieData::class,
            description: "Collections hydrated with `::from()` per item instead of `#[DataCollectionOf]` + `::collect()`"
        );
    }
}
