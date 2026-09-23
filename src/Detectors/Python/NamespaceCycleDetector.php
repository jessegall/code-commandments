<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\NamespaceCycle;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * Two packages importing each other — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\NamespaceCycleDetector}. Every import counts, one
 * moved into a function body to dodge the circular-import error included.
 */
final class NamespaceCycleDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new NamespaceCycle();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase->packageGraph()->arrowsClosingAMutualPair();
    }
}
