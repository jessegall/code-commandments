<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\HandRolledReplace;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A dataclass method rebuilding its own object field by field — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\HandRolledWitherDetector}.
 */
final class HandRolledReplaceDetector implements Detector
{
    public function sin(): Sin
    {
        return new HandRolledReplace();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereCall()
            ->where(static fn (ExprMatch $match): bool => $match->isHandRolledReplace())
            ->get();
    }
}
