<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\Enums;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A `match`/`switch` whose arm conditions are string/int literals that ARE an
 * existing backed enum's case values — dispatching on the loose strings instead
 * of the type that already seals them. Take the enum; match on its cases (or put
 * the behaviour on the case). Points at enums-with-behaviour.
 *
 * Only fires when a real enum mirrors the literals, and not on `match ($x->value)`
 * (that's the {@see EnumValueMatchDetector} homeless-method case).
 */
final class StringMatchMirrorsEnumDetector implements Detector
{
    public function skill(): string
    {
        return 'backend/enums-with-behaviour';
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
