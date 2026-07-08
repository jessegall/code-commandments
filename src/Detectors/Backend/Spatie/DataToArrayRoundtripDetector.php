<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\DataToArrayRoundtripScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataToArrayRoundtrip;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags `X::from(...)->toArray()` whose array result sits in a `::from` slot typed `X` (a nested `Data` or a
 * `#[DataCollectionOf(X)]` element) that re-hydrates it — a build → array → build round-trip. Only fires
 * when the destination slot's element type is exactly the constructed class, so a genuine array sink is spared.
 */
final class DataToArrayRoundtripDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new DataToArrayRoundtrip();
    }

    public function scribe(): string
    {
        return DataToArrayRoundtripScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethod('toArray')
            ->where(static fn (SpatieDataNode $n): bool => $n->isRedundantToArrayRoundtrip())
            ->get();
    }
}
