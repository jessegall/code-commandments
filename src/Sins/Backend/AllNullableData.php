<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\RequiresPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\SpatieData;

final class AllNullableData extends Sin implements RequiresPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'all-nullable-data',
            skill: SpatieData::class,
            description: "All-nullable \"god\" DTO — every field `?T`/defaulted (type doesn't tell the truth)",
            rule: "A DTO's field types must tell the truth — make required fields non-nullable; don't default every field to `?T`/null."
        );
    }
}
