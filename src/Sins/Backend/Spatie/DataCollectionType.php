<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class DataCollectionType extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'data-collection-type',
            skill: SpatieData::class,
            description: "A `Data` property is TYPED as `DataCollection` — it should be `array` (or `Collection`) with `#[DataCollectionOf(X)]`; the `DataCollection` type emits malformed TypeScript and skips element-typed hydration",
            rule: "Never type a `Data` property as `DataCollection`. Type it `array` (preferred) or `Collection` and add `#[DataCollectionOf(X::class)]` — element typing drives hydration, nested validation, and clean TypeScript.",
            suggestion: "`#[DataCollectionOf(NodeData::class)] public readonly array \$nodes;`, not `public readonly DataCollection \$nodes;`.",
        );
    }
}
