<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class ManualInputCast extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'manual-input-cast',
            skill: SpatieData::class,
            description: "A `Data` value-object property is hand-built at every construction site, instead of a `#[WithCast]` / `Castable` that owns the hydration once",
            rule: "Hydrate a value-object property with a `#[WithCast]` (or a `Castable` value object), never by hand-building it at every `new`/`::from` call site.",
            suggestion: "Type the property as the value object and add `#[WithCast(SomeCast::class)]` (or make the value object `Castable`) so the `simple → complex` mapping lives in one place."
        );
    }
}
