<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\PreferOptionalCreate;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/*
 * Righteous twin for both Optional rules: the CORRECT shapes. A `T | Optional` slot DEFAULTS to `new Optional`
 * (a parameter default — where a factory call is illegal, so it is NOT a `prefer-create` sin), and a runtime
 * absence uses the built-in `Optional::create()` factory (not a hand-rolled map, not a raw `new`). Must NOT
 * flag. (A concrete `label` keeps it off AllOptionalData.)
 */
#[Righteous(NullToOptionalMap::class)]
#[Righteous(PreferOptionalCreate::class)]
final class Placement extends Data
{
    public function __construct(
        public readonly string $label,
        public readonly OptCoords|Optional $at = new Optional(),
    ) {}

    public function fallback(bool $absent): OptCoords|Optional
    {
        if ($absent) {
            return Optional::create();
        }

        return OptCoords::from([]);
    }
}
