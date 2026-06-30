<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\SpatieData;

final class NewDataObject extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'new-data-object',
            skill: SpatieData::class,
            description: "`new <Data subclass>` instead of `::from()` / a `fromX()` factory",
            rule: "Build a rich `Data` object via `::from()`/a `fromX()` factory, never `new`.",
            suggestion: "`X::from(...)` (or a `fromY()` factory)."
        );
    }
}
