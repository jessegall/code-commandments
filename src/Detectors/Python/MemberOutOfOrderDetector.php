<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\MemberOutOfOrder;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A constant arriving below a field in a class's head — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\MemberOutOfOrderDetector}. Reported on the
 * constant, the member to move; one below a method is {@see MemberAfterMethodDetector}'s, never both.
 */
final class MemberOutOfOrderDetector implements Detector
{
    public function sin(): Sin
    {
        return new MemberOutOfOrder();
    }

    public function find(Codebase $codebase): array
    {
        $enums = $codebase->enums();

        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->isConstantBelowField($enums))
            ->get();
    }
}
