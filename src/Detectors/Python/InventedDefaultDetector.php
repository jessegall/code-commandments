<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\InventedDefault;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * `f(x or "")` — an empty scalar invented to fill an argument on absence. The Python twin of the
 * backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\ManufacturedFakeFillDetector}: an empty
 * collection is the type's own "no items" and a real default is a choice, so neither is flagged, and
 * `cond and a or b` is the old conditional expression, not a default at all. `x if x else ""` is the
 * same fallback written out, and a lookup helper that `return ""`s on a miss is the same invention one
 * call deeper — found at its `return`.
 */
final class InventedDefaultDetector implements Detector
{
    public function sin(): Sin
    {
        return new InventedDefault();
    }

    public function find(Codebase $codebase): array
    {
        $filled = $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->fallback()->isSomeAnd(static fn (Expr $fallback): bool => $fallback->isEmptyScalar()))
            ->where(static fn (ExprMatch $match): bool => $match->fillsArgument())
            ->reject(static fn (ExprMatch $match): bool => $match->expr->isKeyedDefault())
            ->get();

        $returned = $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->node->returnedValue()->isSomeAnd(static fn (Expr $value): bool => $value->isEmptyScalar()))
            ->where(static fn (NodeMatch $match): bool => $match->enclosingFunction()->isSomeAnd(static fn (FunctionDef $function): bool => $function->isInventingOnMiss()))
            ->get();

        return [...$filled, ...$returned];
    }
}
