<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\EnumCaseOrChain;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An `or` chain testing one subject against several members of one enum the codebase declares — the
 * twin of the backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\EnumCaseOrChainDetector}.
 */
final class EnumCaseOrChainDetector implements Detector
{
    public function sin(): Sin
    {
        return new EnumCaseOrChain();
    }

    public function find(Codebase $codebase): array
    {
        $enums = $codebase->enums();

        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->orChainedCaseClass($enums)->isSome())
            ->reject(static fn (ExprMatch $match): bool => $match->isInsideOr())
            ->get();
    }
}
