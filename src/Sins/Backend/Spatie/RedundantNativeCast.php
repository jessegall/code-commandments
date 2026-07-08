<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieDataHydration;

final class RedundantNativeCast extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'redundant-native-cast',
            skill: SpatieDataHydration::class,
            description: "An enum / date is constructed at a hydration site (`Enum::from(\$x)`, `new DateTime(\$x)`) where the property auto-casts the raw scalar",
            rule: "Pass the raw scalar to an enum / `DateTimeInterface` slot — Spatie auto-casts it; don't construct the value at the hydration site.",
            suggestion: "`'status' => \$raw`, not `'status' => Status::from(\$raw)`."
        );
    }
}
