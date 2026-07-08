<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullableWireObject;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a nullable nested-object field (`T | null`, T a `Data` subclass or enum) on a `#[TypeScript]`
 * Data class, where `T | Optional` belongs: on the wire `?T = null` ships `"field": null`, whereas
 * `Optional` omits it — what the frontend's optional-chaining reads for "absent". Not auto-fixed — it
 * changes the serialized contract, so it's the author's call (the skill teaches the one-line change).
 */
final class NullableWireObjectDetector implements Detector
{
    public function sin(): Sin
    {
        return new NullableWireObject();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereField()
            ->where(static fn (SpatieDataNode $node): bool => $node->nullableWireObject())
            ->get();
    }
}
