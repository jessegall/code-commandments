<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\AllNullableData;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A Spatie Data class whose every promoted field is NULLABLE. The type then tells no
 * truth about what's actually required, so every consumer must re-validate what should
 * have been guaranteed at `::from()`. Make the required fields non-nullable; let `from()`
 * fail hard on a real miss. Points at spatie-data.
 *
 * A non-nullable field with a typed default (`int $x = 0`) is an HONEST optional — a
 * value object with a sensible identity — so a class holding even one is not flagged.
 */
final class AllNullableDataDetector implements Detector
{
    public function sin(): Sin
    {
        return new AllNullableData();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (SpatieDataNode $node): bool => $node->isDataClass())
            ->where(static fn (AstNode $node): bool => $node->everyConstructorParamNullable())
            ->get();
    }
}
