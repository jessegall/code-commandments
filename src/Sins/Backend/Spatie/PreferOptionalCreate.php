<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Spatie\SpatieData;

final class PreferOptionalCreate extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'prefer-optional-create',
            skill: SpatieData::class,
            description: "A raw `new Optional` is constructed in a runtime expression where Spatie's built-in `Optional::create()` factory reads clearer",
            rule: "Use `Optional::create()`, not `new Optional`, everywhere a static call is legal — a parameter/property default must stay `new Optional` (a factory call is illegal there), everywhere else prefer the factory.",
            suggestion: "Replace `new Optional` / `new Optional()` with `Optional::create()`.",
        );
    }
}
