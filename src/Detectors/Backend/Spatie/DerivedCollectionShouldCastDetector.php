<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\DerivedCollectionCast;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags `array_map(E::for(...), $xs)` filling a `#[DataCollectionOf(E)]` slot — a per-item DERIVATION the
 * call site does by hand, where a `#[WithCast]`/`IterableItemCast` on the property should own it. Ceded to
 * {@see ManualHydrationLoopDetector} when the factory is `::from`/`::collect` (auto-hydration, fix = collect);
 * spared when the factory closes over services/`$this` a per-item cast can't reach.
 */
final class DerivedCollectionShouldCastDetector implements Detector
{
    public function sin(): Sin
    {
        return new DerivedCollectionCast();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction('array_map')
            ->where(static fn (SpatieDataNode $n): bool => $n->mappedFactoryDerivesElement())
            ->get();
    }
}
