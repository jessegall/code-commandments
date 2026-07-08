<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieDataHydration;

final class RedundantEnumUnwrap extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'redundant-enum-unwrap',
            skill: SpatieDataHydration::class,
            description: "An enum is unwrapped to `->value` at a hydration site (`'status' => \$order->status->value`) where the property is typed as that enum — Spatie re-casts the scalar straight back to the enum",
            rule: "Pass the enum itself to an enum slot — Spatie's enum cast keeps it; don't destructure it to `->value` at the hydration site only for it to be re-hydrated.",
            suggestion: "`'status' => \$order->status`, not `'status' => \$order->status->value`.",
        );
    }
}
