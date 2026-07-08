<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a hand-rolled null→`Optional` map — a `new Optional` sitting as a ternary fallback
 * (`$x === null ? new Optional : Foo::from($x)`) or the right of a `??` (`expr() ?? new Optional`). That
 * belongs in one named factory (`optionalOrMissing()`), not re-derived at every producer. A `new Optional`
 * parameter DEFAULT or a bare `return new Optional` is the correct shape and is not flagged.
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
            ->whereNew()
            ->where(static fn (SpatieDataNode $node): bool => $node->isOptionalNullFallback())
            ->get();
    }
}
