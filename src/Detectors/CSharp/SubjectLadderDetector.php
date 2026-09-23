<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Sins\CSharp\SubjectLadder;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An `if`/`else if` ladder comparing one subject with constant after constant — the C# twin of the
 * backend's and Python's ladder rules. A constant is what the compiler fixes: a literal, an enum
 * member, a `const`, `typeof` a concrete type. The subjects are compared as fingerprinted expressions,
 * never by their spelling.
 */
final class SubjectLadderDetector implements Detector
{
    /**
     * How many rungs make a ladder — fewer is an ordinary decision.
     */
    private const int RUNGS = 4;

    public function sin(): Sin
    {
        return new SubjectLadder();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->subjectLadderLength() >= self::RUNGS)
            ->get();
    }
}
