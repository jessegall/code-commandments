<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\DictBag;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A dict-typed parameter or local read by string keys — the Python twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ArrayBagDetector}. A named constructor (a
 * `@classmethod` returning `cls(...)`) is where loose data becomes the type, and is left alone.
 */
final class DictBagDetector implements Detector
{
    public function sin(): Sin
    {
        return new DictBag();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->stringKeyBase()->isSome())
            ->where(static fn (ExprMatch $match): bool => $match->isDictKeyRead())
            ->reject(static fn (ExprMatch $match): bool => $match->isWithinNamedConstructor())
            ->get();
    }
}
