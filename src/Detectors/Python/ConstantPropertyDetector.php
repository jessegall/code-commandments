<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\ConstantProperty;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `@property` that never reads the object — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\UselessPropertyHookDetector}. As there, an
 * override answering its subclass's own constant is left alone: the class it is read on IS the object.
 */
final class ConstantPropertyDetector implements Detector
{
    public function sin(): Sin
    {
        return new ConstantProperty();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (NodeMatch $match): bool => $match->isConstantProperty($codebase))
            ->get();
    }
}
