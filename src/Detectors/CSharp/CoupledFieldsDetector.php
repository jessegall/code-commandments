<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\CoupledFields;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A type whose own value fields are really one object — the twin of the backend's and Python's coupled-fields
 * rules, decided by {@see NodeMatch::holdsCoupledFields}.
 */
final class CoupledFieldsDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new CoupledFields();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereType()
            ->where(static fn (NodeMatch $match): bool => $match->holdsCoupledFields($codebase))
            ->get();
    }
}
