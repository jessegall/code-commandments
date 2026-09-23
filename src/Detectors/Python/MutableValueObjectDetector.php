<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\MutableValueObject;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A dataclass value written after it is built — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\MutableValueObjectDetector}. As there, a field
 * the class keeps for itself (`field(init=False)`) is working state, not the value the caller asked for.
 */
final class MutableValueObjectDetector implements Detector
{
    public function sin(): Sin
    {
        return new MutableValueObject();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (NodeMatch $match): bool => $match->isValueWrittenAfterConstruction())
            ->get();
    }
}
