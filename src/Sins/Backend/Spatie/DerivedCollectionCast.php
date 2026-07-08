<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieDataHydration;

final class DerivedCollectionCast extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'derived-collection-cast',
            skill: SpatieDataHydration::class,
            description: "A `#[DataCollectionOf]` is filled by mapping a factory over inputs at the call site, where a `#[WithCast]` should own the derivation",
            rule: "Move an element derivation (`array_map(E::for(...), \$xs)`) into a `#[WithCast]` / `IterableItemCast` on the collection property; pass the raw list.",
            suggestion: "`#[WithCast(SomeCast::class)] public array \$items` — the cast runs `E::for(...)` per item; the call site passes the raw values."
        );
    }
}
