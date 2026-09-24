<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\PositionalTupleReturn;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A member whose declared result is a tuple with unnamed slots — the C# twin of the PHP and Python
 * positional-tuple-return rules. An override or an interface member keeps the signature its contract set.
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
            ->whereNode(static fn (Node $member): bool => $member->returnsPositionalTuple())
            ->reject(static fn (NodeMatch $match): bool => $match->isOverride())
            ->get();
    }
}
