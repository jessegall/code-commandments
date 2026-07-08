<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieDataHydration;

final class RedundantNestedFrom extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'redundant-nested-from',
            skill: SpatieDataHydration::class,
            description: "A nested `X::from([...])` fills a slot the parent `::from` already auto-hydrates from the array",
            rule: "Pass the plain array for a nested `Data` / `#[DataCollectionOf]` slot — don't wrap it in `X::from([...])`.",
            suggestion: "`'slot' => ['a' => 1]` (or `[['a' => 1], ...]` for a collection), not `X::from(['a' => 1])`."
        );
    }
}
