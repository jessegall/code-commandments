<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\RedundantNestedFromScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantNestedFrom;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags `X::from([array literal])` sitting in a parent `SomeData::from([...])` where the destination slot —
 * a nested `Data` property or a `#[DataCollectionOf(X)]` element — auto-hydrates the array itself, so the
 * wrapper is ceremony. An object source (`X::from($model)`), a subtype into a supertype slot, a per-item
 * `array_map`/loop (ceded to {@see ManualHydrationLoopDetector}), and a `#[WithCast]` slot are all spared.
 */
final class RedundantNestedFromDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new RedundantNestedFrom();
    }

    public function scribe(): string
    {
        return RedundantNestedFromScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStaticCall('from')
            ->where(static fn (SpatieDataNode $n): bool => $n->onDataClass())
            ->where(static fn (SpatieDataNode $n): bool => $n->fromArgIsArrayLiteral())
            ->reject(static fn (SpatieDataNode $n): bool => $n->isPerItemHydration())
            ->where(static fn (SpatieDataNode $n): bool => $n->hydratesAnAutoBuiltSlot())
            ->reject(static fn (SpatieDataNode $n): bool => $n->hydrationSlotHasCast())
            ->get();
    }
}
