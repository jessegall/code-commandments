<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\NewDataObjectScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NewDataObject;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects `new Data(...)` instead of `::from(...)` — skips casts, name maps, nested
 * hydration, and factories. Exempts plain Data (scalars/enums only) and parameter-default
 * positions. Points at spatie-data.
 */
final class NewDataObjectDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new NewDataObject();
    }

    public function scribe(): string
    {
        return NewDataObjectScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNew()
            ->where(static fn (SpatieDataNode $node): bool => $node->isNewData())
            ->reject(static fn (AstNode $node): bool => $node->isParameterDefault())
            ->where(static fn (SpatieDataNode $node): bool => $node->isRichData())
            ->get();
    }
}
