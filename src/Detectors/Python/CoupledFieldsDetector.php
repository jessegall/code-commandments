<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\FieldClumps;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\CoupledFields;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A class whose own value fields are really one object — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\CoupledFieldsDetector}, decided by {@see FieldClumps}.
 */
final class CoupledFieldsDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new CoupledFields();
    }

    public function find(Codebase $codebase): array
    {
        $clumps = new FieldClumps($codebase);

        return $codebase
            ->whereClass()
            ->where(static fn (NodeMatch $match): bool => $match->node instanceof ClassDef && $clumps->isCoupled($match->node))
            ->get();
    }
}
