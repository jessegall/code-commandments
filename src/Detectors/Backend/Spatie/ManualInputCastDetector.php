<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualInputCast;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a `Data` value-object property that is hand-built at EVERY `::from()` site (via the whole-program
 * {@see \JesseGall\CodeCommandments\Ast\Spatie\DataConstructions} index), where a `#[WithCast]` /
 * `Castable` should own the mapping once.
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
