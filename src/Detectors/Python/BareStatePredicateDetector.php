<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\BareStatePredicate;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `bool` about the receiver named as a bare verb — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\BareStatePredicateDetector}, over the same verb
 * lexicon, read in snake_case.
 */
final class BareStatePredicateDetector implements Detector
{
    public function sin(): Sin
    {
        return new BareStatePredicate();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (NodeMatch $match): bool => $match->isBareStatePredicate($codebase))
            ->get();
    }
}
