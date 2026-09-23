<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\MutableStaticState;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A write to state no instance owns — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\MutableStaticStateDetector}. The write is
 * reported, not the declaration: the write is where the coupling is made.
 */
final class MutableStaticStateDetector implements Detector
{
    public function sin(): Sin
    {
        return new MutableStaticState();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->isStaticStateWrite())
            ->get();
    }
}
