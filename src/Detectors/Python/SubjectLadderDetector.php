<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\SubjectLadder;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An `if`/`elif` chain testing one subject against constant after constant — the Python twin of the
 * Vue {@see \JesseGall\CodeCommandments\Detectors\Frontend\SwitchCaseDetector}, at the backend ladder's
 * length: fewer rungs are an ordinary decision, not a dispatch.
 */
final class SubjectLadderDetector implements Detector
{
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
