<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Node\AnnAssign;
use JesseGall\CodeCommandments\Py\Node\Node;
use JesseGall\CodeCommandments\Py\Node\Param;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\BlankStringDefault;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `str` parameter or field defaulting to the blank whose own scope then asks whether it is blank —
 * the twin of the backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\BlankStringDefaultDetector}.
 * A blank that is never asked about means "empty" — a joiner, a buffer — and is left alone.
 */
final class BlankStringDefaultDetector implements Detector
{
    public function sin(): Sin
    {
        return new BlankStringDefault();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node) => $node instanceof Param || $node instanceof AnnAssign)
            ->where(static fn (NodeMatch $match): bool => $match->isBlankStringDefault())
            ->where(static fn (NodeMatch $match): bool => $match->defaultedNameTestedForBlankness())
            ->reject(static fn (NodeMatch $match): bool => $match->isFieldOfDataBuiltClass($codebase))
            ->reject(static fn (NodeMatch $match): bool => $match->isParameterOfADispatchedMethod($codebase))
            ->reject(static fn (NodeMatch $match): bool => $match->isParameterEveryCallFills($codebase))
            ->get();
    }
}
