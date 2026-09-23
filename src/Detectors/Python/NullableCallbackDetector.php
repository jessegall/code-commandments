<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\NullableCallback;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A function taking a `None`-defaulted callable it then asks about — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\NullableCallbackDetector}. A callback only
 * passed on is left alone: the question is asked, if anywhere, where it is called.
 */
final class NullableCallbackDetector implements Detector
{
    public function sin(): Sin
    {
        return new NullableCallback();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->hasNullNormalisedOptionalCallback())
            ->get();
    }
}
