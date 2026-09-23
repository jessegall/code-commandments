<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\PositionalTupleReturn;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A tuple of different things handed back by position — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\PositionalTupleReturnDetector}. As there, a
 * return declared a sequence of one kind is taken at its word, and a dunder protocol method returns the
 * tuple the language asks of it.
 */
final class PositionalTupleReturnDetector implements Detector
{
    public function sin(): Sin
    {
        return new PositionalTupleReturn();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->isPositionalTuple())
            ->where(static fn (ExprMatch $match): bool => $match->isReturnedValue())
            ->reject(static fn (ExprMatch $match): bool => $match->isInSequenceFunction())
            ->reject(static fn (ExprMatch $match): bool => $match->isInContractMethod($codebase))
            ->get();
    }
}
