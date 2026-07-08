<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\DataCollectionTypeScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataCollectionType;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a `Data` property typed as `DataCollection` — it must be `array` (preferred) or `Collection` with
 * `#[DataCollectionOf(X)]`. The `DataCollection` type generates malformed TS (`undefined<number, X>`) and
 * skips element-typed hydration/validation. Auto-fixable when the element type is recoverable.
 */
final class DataCollectionTypeDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new DataCollectionType();
    }

    public function scribe(): string
    {
        return DataCollectionTypeScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereField()
            ->where(static fn (SpatieDataNode $node): bool => $node->propertyTypedAsDataCollection())
            ->get();
    }
}
