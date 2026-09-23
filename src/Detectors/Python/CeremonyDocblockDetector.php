<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\CeremonyDocblock;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A docstring that only repeats the annotations — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\CeremonyDocblockDetector}.
 */
final class CeremonyDocblockDetector implements Detector
{
    public function sin(): Sin
    {
        return new CeremonyDocblock();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->node instanceof FunctionDef && $match->node->hasCeremonyDocstring())
            ->get();
    }
}
