<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Sins\CSharp\SwallowedException;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `catch` that catches everything and makes it vanish — the C# twin of the backend's and Python's
 * swallowed-exception rules. What it catches is the type the compiler resolves, never its spelling; a
 * `catch` that names the failure it expects, or filters it with `when`, has decided what that failure
 * means, and is left alone.
 */
final class SwallowedExceptionDetector implements Detector
{
    public function sin(): Sin
    {
        return new SwallowedException();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node): bool => $node->is('CatchClause'))
            ->where(static fn (NodeMatch $match): bool => $match->node->isBroadCatch())
            ->where(static fn (NodeMatch $match): bool => $match->node->swallows())
            ->get();
    }
}
