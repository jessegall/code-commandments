<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\ConstructorSideEffect;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A class whose `__init__` acts on a collaborator and discards the result — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ConstructorSideEffectDetector}.
 */
final class ConstructorSideEffectDetector implements Detector
{
    public function sin(): Sin
    {
        return new ConstructorSideEffect();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (NodeMatch $match): bool => $match->constructorHasSideEffect())
            ->get();
    }
}
