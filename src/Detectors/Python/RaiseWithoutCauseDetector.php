<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\RaiseWithoutCause;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A new exception raised from an `except` block without `from` — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\WrappingWithoutCauseDetector}.
 */
final class RaiseWithoutCauseDetector implements Detector
{
    public function sin(): Sin
    {
        return new RaiseWithoutCause();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->isRaiseWithoutCause())
            ->get();
    }
}
