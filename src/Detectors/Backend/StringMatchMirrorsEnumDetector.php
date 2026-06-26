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
        return 'enums-with-behaviour';
    }

    public function find(Codebase $codebase): array
    {
        $enums = Enums::casesByEnum($codebase);

        return $codebase
            ->where(static fn (AstNode $node): bool => $node->armConditionLiterals() !== [])
            ->reject(static fn (AstNode $node): bool => $node->isMatchOnEnumValue())
            ->where(static fn (AstNode $node): bool => self::mirrorsAnEnum($node->armConditionLiterals(), $enums))
            ->get();
    }

    /**
     * @param  list<string>  $literals
     * @param  array<string, list<string>>  $enums
     */
    private static function mirrorsAnEnum(array $literals, array $enums): bool
    {
        $literals = array_unique($literals);

        if (count($literals) < 2) {
            return false;
        }

        foreach ($enums as $cases) {
            if (array_diff($literals, $cases) === []) {
                return true; // every arm literal is a case of this enum
            }
        }

        return false;
    }
}
