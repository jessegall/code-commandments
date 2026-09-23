<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\PlaceholderFilledData;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A dataclass built with `""` in a field it requires as text — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\Spatie\PlaceholderFilledDataDetector}. Narrow
 * as there: `0` and `False` are ordinary values, and an optional field already admits absence.
 */
final class PlaceholderFilledDataDetector implements Detector
{
    public function sin(): Sin
    {
        return new PlaceholderFilledData();
    }

    public function find(Codebase $codebase): array
    {
        $dataclasses = $codebase->dataclasses();

        return $codebase
            ->whereCall()
            ->where(static fn (ExprMatch $match): bool => $match->fillsRequiredTextWithBlank($dataclasses))
            ->get();
    }
}
