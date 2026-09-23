<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\InLiteralsMirrorsEnum;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An `in` test against literals that are all one declared enum's values — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\InArrayMirrorsEnumDetector}.
 */
final class InLiteralsMirrorsEnumDetector implements Detector
{
    public function sin(): Sin
    {
        return new InLiteralsMirrorsEnum();
    }

    public function find(Codebase $codebase): array
    {
        $enums = $codebase->enums();

        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $enums->holdAll($expression->membershipLiteralKeys()))
            ->get();
    }
}
