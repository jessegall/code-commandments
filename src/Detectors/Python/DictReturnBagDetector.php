<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\DictReturnBag;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A dict of two or more named fields handed back from a function — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ArrayReturnBagDetector}, with its exemptions:
 * a spread, a nested payload, keys that name external things rather than fields, a JSON schema, a table of one class's members, the projection of one typed
 * object, a `TypedDict` return, and a method whose contract is its base's or the language's.
 */
final class DictReturnBagDetector implements Detector
{
    public function sin(): Sin
    {
        return new DictReturnBag();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->stringKeyCount() >= 2)
            ->where(static fn (ExprMatch $match): bool => $match->isReturnedValue())
            ->where(static fn (ExprMatch $match): bool => $match->expr->hasFieldNameKeys())
            ->reject(static fn (ExprMatch $match): bool => $match->expr->spreadsAnother())
            ->reject(static fn (ExprMatch $match): bool => $match->expr->hasNestedCollectionValue())
            ->reject(static fn (ExprMatch $match): bool => $match->expr->isJsonSchema())
            ->reject(static fn (ExprMatch $match): bool => $match->expr->isMemberTable())
            ->reject(static fn (ExprMatch $match): bool => $match->isProjection())
            ->reject(static fn (ExprMatch $match): bool => $match->isInTypedDictFunction($codebase))
            ->reject(static fn (ExprMatch $match): bool => $match->isInContractMethod($codebase))
            ->get();
    }
}
