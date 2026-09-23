<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\DeepNesting;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An `if`, loop or `match` that opens a fourth level of choices inside one function — the arrow the
 * backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\DeepNestingDetector} finds, counted
 * over every kind of choice Python writes. Reported once per arrow, where it crosses the limit.
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
            ->reject(static fn (NodeMatch $match): bool => $match->isElif())
            ->where(static fn (NodeMatch $match): bool => $match->branchingDepth() === self::LIMIT)
            ->get();
    }
}
