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
 * Constructing a RICH Spatie `Data` object with `new` instead of `::from()` — the
 * raw `new` skips the work `::from()` does: a cast, a name map, a nested-Data
 * hydration, or a magic `fromX()` factory. Points at spatie-data.
 *
 * A PLAIN Data class (only scalar/enum props, no cast/map/nest/factory) is exempt:
 * there `::from()` and `new` are equivalent, so `new` tells no lie. A `new` in
 * PARAMETER-DEFAULT position is exempt — the one place the skill permits `new`.
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
