<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\InventedDefault;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * `f(x or "")` — an empty scalar invented to fill an argument on absence. The Python twin of the
 * backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\ManufacturedFakeFillDetector}: an empty
 * collection is the type's own "no items" and a real default is a choice, so neither is flagged, and
 * `cond and a or b` is the old conditional expression, not a default at all.
 */
final class InventedDefaultDetector implements Detector
{
    public function sin(): Sin
    {
        return new InventedDefault();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->is(ExprKind::Binary) && $expression->get('op') === 'or')
            ->where(static fn (ExprMatch $match): bool => $match->expr->get('right')->isEmptyScalar())
            ->reject(static fn (ExprMatch $match): bool => $match->expr->get('left')->is(ExprKind::Binary) && $match->expr->get('left')->get('op') === 'and')
            ->where(static fn (ExprMatch $match): bool => $match->fillsArgument())
            ->get();
    }
}
