<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NamespaceGraph;
use JesseGall\CodeCommandments\Sins\CSharp\NamespaceCycle;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * Two of the project's namespaces that each use the other, found on the thinner direction — the twin of the
 * backend's and Python's namespace-cycle rules.
 */
final class NamespaceCycleDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new NamespaceCycle();
    }

    public function find(Codebase $codebase): array
    {
        return new NamespaceGraph($codebase)->independentArrows()->closingAMutualPair();
    }
}
