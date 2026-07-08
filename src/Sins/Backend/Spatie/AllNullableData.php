<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class AllNullableData extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'all-nullable-data',
            skill: SpatieData::class,
            description: "All-nullable \"god\" DTO — every field `?T`/defaulted (type doesn't tell the truth)",
            rule: "A DTO's field types must tell the truth — make required fields non-nullable; don't default every field to `?T`/null. If every field genuinely IS optional and same-shaped (a callback bag, a filter set, a money breakdown), make each non-nullable with a Null Object / identity default on the value type instead.",
            suggestion: "Retype each field to the truth: required → non-nullable, no default; genuinely-absent → `T|Optional = new Optional()` (dropped from output, not `null`); always-present-but-emptyable → a Null Object / identity default (`Grid \$grid = new Grid()`, `Status \$s = Status::Default`). If a whole SUB-object may be absent, put the optional on the CONTAINER field (`Type|Optional \$x = new Optional()`) and keep that type's leaves concrete — don't scatter `?T`/`Optional` across every leaf."
        );
    }
}
