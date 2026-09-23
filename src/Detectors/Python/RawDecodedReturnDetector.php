<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\RawDecodedReturn;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A raw `json.loads(...)` handed back from a boundary — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\RawDecodedArrayReturnDetector}. A round trip of
 * our own value, a `TypedDict` return, and a method whose contract is its base's are left alone.
 */
final class RawDecodedReturnDetector implements Detector
{
    public function sin(): Sin
    {
        return new RawDecodedReturn();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Expr $expression): bool => $expression->isJsonDecode())
            ->where(static fn (ExprMatch $match): bool => $match->isReturnedValue())
            ->reject(static fn (ExprMatch $match): bool => $match->expr->decodesItsOwnEncoding())
            ->reject(static fn (ExprMatch $match): bool => $match->isInTypedDictFunction($codebase))
            ->reject(static fn (ExprMatch $match): bool => $match->isInContractMethod($codebase))
            ->get();
    }
}
