<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a hand-rolled null→`Optional` map — an "absent" Optional (`new Optional` OR the preferred
 * `Optional::create()`) sitting as a ternary fallback (`$x === null ? Optional::create() : Foo::from($x)`) or
 * the right of a `??` (`expr() ?? Optional::create()`). That belongs in one named factory
 * (`optionalOrMissing()`), not re-derived at every producer. A `new Optional` parameter DEFAULT or a bare
 * `return Optional::create()` is the correct shape and is not flagged, and neither is the shared
 * `optionalOrMissing()` factory itself — the `$x === null ? Optional::create() : static::from($x)` home the fix
 * tells producers to create; that is the one place the map is supposed to live.
 */
final class NullToOptionalMapDetector implements Detector
{
    public function sin(): Sin
    {
        return new NullToOptionalMap();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (SpatieDataNode $node): bool => $node->isOptionalNullFallback())
            ->reject(static fn (SpatieDataNode $node): bool => $node->isSharedOptionalFactory())
            ->get();
    }
}
