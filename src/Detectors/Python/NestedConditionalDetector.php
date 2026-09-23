<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\NestedConditional;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A conditional expression nested in another's branch, flagged once at its outermost — the twin of the
 * backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\NestedTernaryDetector}.
 */
final class NestedConditionalDetector implements Detector
{
    public function sin(): Sin
    {
        return new NestedConditional();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->is(ExprKind::Conditional))
            ->where(static fn (ExprMatch $match): bool => $match->isOutermostNestedConditional())
            ->get();
    }
}
