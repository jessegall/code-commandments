<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\ShortCircuitStatement;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An `and`/`or` standing as a whole statement, its value read by nothing — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ShortCircuitStatementDetector}.
 */
final class ShortCircuitStatementDetector implements Detector
{
    public function sin(): Sin
    {
        return new ShortCircuitStatement();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->isShortCircuit())
            ->where(static fn (ExprMatch $match): bool => $match->resultIsDiscarded())
            ->get();
    }
}
