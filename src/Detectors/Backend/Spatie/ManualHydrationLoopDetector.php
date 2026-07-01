<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\ManualHydrationLoopScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualHydrationLoop;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * `<Data>::from(...)` called per item of a collection — inside a `foreach`/`for`/
 * `while` loop, or as an `array_map` callback. Spatie does this in one pass: a
 * `#[DataCollectionOf]` property plus `::collect()`. The loop/map is the mapping that
 * belongs to the framework. Points at spatie-data.
 */
final class ManualHydrationLoopDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new ManualHydrationLoop();
    }

    public function scribe(): string
    {
        return ManualHydrationLoopScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStaticCall('from')
            ->where(static fn (SpatieDataNode $node): bool => $node->onDataClass())
            ->where(static fn (SpatieDataNode $node): bool => $node->isPerItemHydration())
            ->reject(static fn (SpatieDataNode $node): bool => $node->isWithinTolerantCatch())
            ->reject(static fn (SpatieDataNode $node): bool => $node->isKeyedMapAssignment())
            ->get();
    }
}
