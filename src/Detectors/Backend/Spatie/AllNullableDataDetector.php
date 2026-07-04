<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\NullObjectDefaultScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\AllNullableData;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects Spatie Data classes where every field is nullable; make required fields non-nullable
 * so `from()` fails on a real miss.
 */
final class AllNullableDataDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new AllNullableData();
    }

    public function scribe(): string
    {
        return NullObjectDefaultScribe::class;
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
