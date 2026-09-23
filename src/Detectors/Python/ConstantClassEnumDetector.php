<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\ConstantClassEnum;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A class of nothing but scalar constants — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ConstClassEnumDetector}.
 */
final class ConstantClassEnumDetector implements Detector
{
    public function sin(): Sin
    {
        return new ConstantClassEnum();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (NodeMatch $match): bool => $match->isScalarConstantClass())
            ->get();
    }
}
