<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\NarratedCommand;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A command named in the third person — `def hides(self) -> None` — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\NarratedCommandDetector}, over the same verb
 * lexicon, read in snake_case.
 */
final class NarratedCommandDetector implements Detector
{
    public function sin(): Sin
    {
        return new NarratedCommand();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (NodeMatch $match): bool => $match->isNarratedCommand($codebase))
            ->get();
    }
}
