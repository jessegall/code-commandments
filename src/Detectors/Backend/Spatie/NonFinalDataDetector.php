<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\NonFinalDataScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NonFinalData;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects non-final Spatie Data classes (DTOs are values, not bases). Exempt: classes that
 * other classes actually extend. Points at spatie-data.
 */
final class NonFinalDataDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new NonFinalData();
    }

    public function scribe(): string
    {
        return NonFinalDataScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (SpatieDataNode $node): bool => $node->isDataClass())
            ->where(static fn (AstNode $node): bool => $node->isNonFinalClass())
            ->reject(static fn (AstNode $node): bool => $codebase->hasSubclass($node->enclosingClassName()))
            ->get();
    }
}
