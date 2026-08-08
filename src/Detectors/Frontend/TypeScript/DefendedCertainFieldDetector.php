<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend\TypeScript;

use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Sins\Frontend\TypeScript\DefendedCertainField;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Ts\ExprMatch;
use JesseGall\CodeCommandments\Ts\Node\FieldDecl;
use JesseGall\CodeCommandments\Vue\Codebase;

/**
 * An `?.` on one of the enclosing class's OWN fields, where that field is declared total — a guard
 * against a case the type rules out. Only a field the same class declares is judged, because the
 * class is the authority on its own state and anything reached through another object is not
 * knowable here without a checker.
 */
final class DefendedCertainFieldDetector implements Detector
{
    public function sin(): Sin
    {
        return new DefendedCertainField();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMember()
            ->where(static fn (ExprMatch $match): bool => $match->expr->isOptionalChain())
            ->where(static fn (ExprMatch $match): bool => $match->ownField($match->expr->ownFieldRead())->isSomeAnd(
                static fn (FieldDecl $field): bool => ! $field->isOptional(),
            ))
            ->get();
    }
}
