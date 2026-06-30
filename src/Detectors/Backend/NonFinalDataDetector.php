<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\NonFinalData;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A Spatie `Data` class that is not declared `final`. A DTO is a value, not a base
 * to extend — leaving it open invites subclasses that quietly change its shape and
 * break the "the type tells the truth" contract. Seal it. Points at spatie-data.
 *
 * A Data class that other classes ACTUALLY extend (a morphable base) is exempt —
 * `final` plus `extends` is a fatal error, so flagging it points at an impossible
 * fix. Only a sealable leaf is a sin.
 */
final class NonFinalDataDetector implements Detector
{
    private const string DATA = 'Spatie\\LaravelData\\Data';

    public function sin(): Sin
    {
        return new NonFinalData();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClassExtending(self::DATA)
            ->where(static fn (AstNode $node): bool => $node->isNonFinalClass())
            ->reject(static fn (AstNode $node): bool => $codebase->hasSubclass($node->enclosingClassName()))
            ->get();
    }
}
