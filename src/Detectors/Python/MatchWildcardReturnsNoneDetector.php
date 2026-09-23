<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\MatchWildcardReturnsNone;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `match` over an enum's members whose wildcard swallows the unhandled ones into nothing — the twin
 * of the backend's {@see \JesseGall\CodeCommandments\Detectors\Backend\MatchDefaultReturnsNullDetector}.
 * As there, an arm that already answers `None` makes `None` an honest answer, and a subject that is not
 * a closed set has no member to forget.
 */
final class MatchWildcardReturnsNoneDetector implements Detector
{
    public function sin(): Sin
    {
        return new MatchWildcardReturnsNone();
    }

    public function find(Codebase $codebase): array
    {
        $enums = $codebase->enums();

        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->isEnumMatchWithAbsentWildcard($enums))
            ->get();
    }
}
