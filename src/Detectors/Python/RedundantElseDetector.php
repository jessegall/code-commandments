<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\RedundantElse;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An `else:` after an `if` branch that already left — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\RedundantElseDetector}. A loop's `else` means "no
 * `break`" and is a different construct altogether.
 */
final class RedundantElseDetector implements Detector
{
    public function sin(): Sin
    {
        return new RedundantElse();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->hasRedundantElse())
            ->get();
    }
}
