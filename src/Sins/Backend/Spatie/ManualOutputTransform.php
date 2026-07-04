<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\PageObjects;

final class ManualOutputTransform extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'manual-output-transform',
            skill: PageObjects::class,
            description: "A `Data` computed slot hand-flattens a value object into a wire array, instead of a `#[WithTransformer]` that owns the serialized shape",
            rule: "Shape a property's wire output with a `#[WithTransformer]` (+ a matching `#[TypeScriptType]`), never a computed getter that hand-builds the reshaped array.",
            suggestion: "Keep the real value-object type and add `#[WithTransformer(SomeTransformer::class)]` — plus `#[TypeScriptType(...)]` so the generated TypeScript matches the transformed shape."
        );
    }
}
