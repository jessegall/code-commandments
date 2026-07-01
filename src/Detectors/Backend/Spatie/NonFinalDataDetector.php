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
 * A Spatie `Data` class that is not declared `final`. A DTO is a value, not a base
 * to extend — leaving it open invites subclasses that quietly change its shape and
 * break the "the type tells the truth" contract. Seal it. Points at spatie-data.
 *
 * A Data class that other classes ACTUALLY extend (a morphable base) is exempt —
 * `final` plus `extends` is a fatal error, so flagging it points at an impossible fix.
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
