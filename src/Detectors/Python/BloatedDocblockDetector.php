<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\BloatedDocblock;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A class docstring that runs to an essay — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\BloatedDocblockDetector}.
 */
final class BloatedDocblockDetector implements Detector
{
    public function sin(): Sin
    {
        return new BloatedDocblock();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (NodeMatch $match): bool => $match->node instanceof ClassDef && $match->node->hasMultiParagraphDocstring())
            ->get();
    }
}
