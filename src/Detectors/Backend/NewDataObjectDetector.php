<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\NewDataObject;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\DataClassShape;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\NewDataObjectScribe;

/**
 * Constructing a RICH Spatie `Data` object with `new` instead of `::from()` — the
 * raw `new` skips the work `::from()` does: a cast, a name map, a nested-Data
 * hydration, or a magic `fromX()` factory. Points at spatie-data.
 *
 * A PLAIN Data class (only scalar/enum props, no cast/map/nest/factory) is exempt:
 * there `::from()` and `new` are equivalent, so `new` tells no lie. The smell is
 * `new` that silently bypasses a pipeline the class actually has — see
 * {@see DataClassShape}.
 *
 * A `new` in PARAMETER-DEFAULT position (`function f(Summary $s = new Summary())`)
 * is exempt — the one place the skill permits `new` regardless of shape.
 */
final class NewDataObjectDetector implements Detector, Repentable
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

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
        $shape = DataClassShape::forCodebase($codebase);

        return $codebase
            ->whereNewExtending(self::DATA)
            ->reject(static fn (AstNode $node): bool => $node->isParameterDefault())
            ->where(static fn (AstNode $node): bool => $shape->isRich($node->newClassName(), $codebase))
            ->get();
    }
}
