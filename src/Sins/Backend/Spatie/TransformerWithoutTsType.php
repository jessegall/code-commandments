<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\PageObjects;

final class TransformerWithoutTsType extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'transformer-without-ts-type',
            skill: PageObjects::class,
            description: "A `#[WithTransformer]` changes a property's wire shape but has no paired `#[TypeScriptType]`/`#[LiteralTypeScriptType]`, so the generated TypeScript keeps the wrong (PHP) type",
            rule: "Pair every custom `#[WithTransformer]` with a `#[TypeScriptType]` / `#[LiteralTypeScriptType]` that declares the transformed wire shape.",
            suggestion: "Add `#[TypeScriptType('...')]` (or `#[LiteralTypeScriptType(...)]`) stating the type the transformer serializes to, so the generated frontend type matches the wire."
        );
    }
}
