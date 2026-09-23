<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\ConditionalStatement;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A conditional expression standing as a whole statement, its value read by nothing — the twin of the
 * backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\TernaryStatementDetector}.
 */
final class ConditionalStatementDetector implements Detector
{
    public function sin(): Sin
    {
        return new ConditionalStatement();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->is(ExprKind::Conditional))
            ->where(static fn (ExprMatch $match): bool => $match->resultIsDiscarded())
            ->get();
    }
}
