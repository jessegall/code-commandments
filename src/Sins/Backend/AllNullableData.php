<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\SpatieData;

final class AllNullableData extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'all-nullable-data',
            skill: SpatieData::class,
            description: "All-nullable \"god\" DTO — every field `?T`/defaulted (type doesn't tell the truth)"
        );
    }
}
