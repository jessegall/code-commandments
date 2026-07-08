<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\HookMissingComputedScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\HookMissingComputed;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a get-only property hook on a `Data` class that lacks `#[Computed]`. Spatie reads such a virtual
 * property as a hydration input — expecting it in `::from()`, which a get-only hook cannot receive — so the
 * class crashes or silently drops the field. Mechanically fixable: stamp `#[Computed]`.
 */
final class HookMissingComputedDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new HookMissingComputed();
    }

    public function scribe(): string
    {
        return HookMissingComputedScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereGetterHook()
            ->where(static fn (SpatieDataNode $node): bool => $node->hookMissingComputed())
            ->get();
    }
}
