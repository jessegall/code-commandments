<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\LoopWrappedInIf;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A loop whose whole body is one `if` around real work — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\LoopInvertedGuardDetector}.
 */
final class LoopWrappedInIfDetector implements Detector
{
    public function sin(): Sin
    {
        return new LoopWrappedInIf();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->isSoleLoopBodyGuard())
            ->get();
    }
}
