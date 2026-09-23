<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\ScratchStateRestore;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A method saving and restoring its own attribute around the call — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ScratchStateRestoreDetector}.
 */
final class ScratchStateRestoreDetector implements Detector
{
    public function sin(): Sin
    {
        return new ScratchStateRestore();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->hasOwnStateSaveAndRestore())
            ->get();
    }
}
