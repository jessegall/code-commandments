<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualInputCast;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `Data` value-object property that is HAND-BUILT at every place its object is constructed — a
 * `new D(price: new Money(...))` / `D::from(['price' => new Money(...)])` at every call site. The
 * `simple → complex` mapping is then re-authored per caller; a `#[WithCast]` (or a `Castable` value
 * object) should own it once. Points at spatie-data.
 *
 * This is a CROSS-FILE detector: the property is only flagged when the whole-program
 * {@see \JesseGall\CodeCommandments\Ast\Spatie\DataConstructions} index shows EVERY construction site
 * hand-maps it — a single clean site (an opaque `::from($model)`, a passed-through value) spares it, so
 * a `Data` you can build straight from a model is never flagged.
 */
final class ManualInputCastDetector implements Detector
{
    public function sin(): Sin
    {
        return new ManualInputCast();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereField()
            ->where(static fn (SpatieDataNode $node): bool => $node->alwaysHandBuiltAtConstruction())
            ->get();
    }
}
