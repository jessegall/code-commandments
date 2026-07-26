<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\RedundantArrowReturnTypeScribe;
use JesseGall\CodeCommandments\Sins\Backend\RedundantArrowReturnType;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An arrow function carrying a return type its own expression already proves — `fn (): string =>
 * $this->name` where `$name` IS a `string`. One expression, one obvious type, and the annotation adds
 * reading without adding meaning. The proof has to be exact because the fix DELETES the type: only
 * where {@see \JesseGall\CodeCommandments\Ast\Support\ExpressionType} can name what the expression
 * yields, and that name matches the declaration character for character, nullability included.
 * Whatever cannot be proven — a ternary, a coalesce, a call on an inferred receiver — is ambiguous,
 * and an ambiguous type is exactly the one worth writing down. Points at type-honesty.
 */
final class RedundantArrowReturnTypeDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new RedundantArrowReturnType();
    }

    public function scribe(): string
    {
        return RedundantArrowReturnTypeScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereArrowFunction()
            ->where(fn (AstNode $n): bool => $n->hasReturnType())
            ->where(fn (NodeMatch $n): bool => $n->returnTypeRestatesItsExpression())
            ->get();
    }
}
