<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\AllOptionalData;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects a `Data` class where EVERY promoted property is `T|Optional` — the all-optional sibling of the
 * all-nullable "god" DTO. The type promises nothing is ever present; the honest shape gives each leaf a
 * concrete default and moves the optionality onto the CONTAINER field where the object is used. Not
 * auto-fixable — the fix reshapes this class AND its use sites, an author judgment.
 */
final class AllOptionalDataDetector implements Detector
{
    public function sin(): Sin
    {
        return new AllOptionalData();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (SpatieDataNode $node): bool => $node->isDataClass())
            ->where(static fn (SpatieDataNode $node): bool => $node->everyConstructorParamOptional())
            ->get();
    }
}
