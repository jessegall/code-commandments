<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\StringMatchMirrorsEnum;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\Enums;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects `match`/`switch` arm conditions that are string/int literals mirroring an existing
 * backed enum's case values. Dispatch on the type itself, not loose strings. Ignores
 * `match ($x->value)` (see {@see EnumValueMatchDetector}).
 */
final class StringMatchMirrorsEnumDetector implements Detector
{
    public function sin(): Sin
    {
        return new StringMatchMirrorsEnum();
    }

    public function find(Codebase $codebase): array
    {
        $enums = Enums::casesByEnum($codebase);

        return $codebase
            ->where(static fn (AstNode $node): bool => $node->armConditionLiterals() !== [])
            ->reject(static fn (AstNode $node): bool => $node->isMatchOnEnumValue())
            ->where(static fn (AstNode $node): bool => Enums::mirroredBy($node->armConditionLiterals(), $enums))
            ->get();
    }
}
