<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class HookMissingComputed extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'hook-missing-computed',
            skill: SpatieData::class,
            description: "A get-only property HOOK on a `Data` class lacks `#[Computed]` — Spatie reads the virtual property as a hydration INPUT, expects it in `::from()`, and crashes or silently drops it",
            rule: "Mark every get-only property hook on a `Data` class `#[Computed]`, so Spatie treats it as an output-only computed value, not a required hydration input.",
            suggestion: "Add `#[Computed]` above the property: `#[Computed] public array \$docks { get => \$this->dockSet->all(); }`.",
        );
    }
}
