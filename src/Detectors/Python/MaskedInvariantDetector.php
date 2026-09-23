<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\OwnStateMask;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\MaskedInvariant;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A fake answer masking the object's own scratch state — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\MaskedInvariantDetector}, decided by {@see OwnStateMask}.
 */
final class MaskedInvariantDetector implements Detector
{
    public function sin(): Sin
    {
        return new MaskedInvariant();
    }

    public function find(Codebase $codebase): array
    {
        $mask = new OwnStateMask();

        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->is(ExprKind::Conditional) || $expression->isCall())
            ->where(static fn (ExprMatch $match): bool => $mask->masksOwnState($match->expr, $match->module))
            ->get();
    }
}
