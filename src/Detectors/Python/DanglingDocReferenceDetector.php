<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Docstring;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\DanglingDocReference;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A docstring cross-reference to a first-party name nothing declares — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\DanglingDocReferenceDetector}. A reference into a
 * package the codebase does not hold cannot be checked from here, so it is left alone.
 */
final class DanglingDocReferenceDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new DanglingDocReference();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->node->docstring()->isSomeAnd(
                static fn (string $docstring): bool => array_any(
                    Docstring::references($docstring),
                    static fn (string $reference): bool => $codebase->ownsPackage(explode('.', $reference)[0]) && ! $codebase->resolves($reference),
                ),
            ))
            ->get();
    }
}
