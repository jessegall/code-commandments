<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Sins\CSharp\DeepNesting;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An `if`, loop or `switch` that opens a fourth level of choices inside one C# method — the arrow the
 * backend's and Python's nesting rules find, counted over every kind of choice C# writes. An `else if`
 * is a rung of its ladder, and `try`, `using` and `lock` are boundaries, so none is a level. Reported
 * once per arrow, where it crosses the limit.
 */
final class DeepNestingDetector implements Detector
{
    /**
     * How many choices a construct may already sit inside; the one past this is flagged.
     */
    private const int LIMIT = 3;

    public function sin(): Sin
    {
        return new DeepNesting();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->node->isBranchingConstruct())
            ->reject(static fn (NodeMatch $match): bool => $match->isElseIf())
            ->where(static fn (NodeMatch $match): bool => $match->branchingDepth() === self::LIMIT)
            ->get();
    }
}
