<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\ConditionalSpread;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A conditional spread into an empty collection — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ConditionalArraySpreadDetector}.
 */
final class ConditionalSpreadDetector implements Detector
{
    public function sin(): Sin
    {
        return new ConditionalSpread();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->isConditionalSpread())
            ->get();
    }
}
