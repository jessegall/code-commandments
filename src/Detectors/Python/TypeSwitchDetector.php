<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\TypeSwitch;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An `isinstance` ladder over the codebase's own classes — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\TypeSwitchDetector}. The fix is a method on the
 * shared base, so it is only owed where the codebase declares every class the ladder asks about.
 */
final class TypeSwitchDetector implements Detector
{
    public function sin(): Sin
    {
        return new TypeSwitch();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereCall()
            ->where(static fn (ExprMatch $match): bool => $match->isTypeSwitchHead())
            ->where(static fn (ExprMatch $match): bool => array_all($match->typeSwitchClasses(), $codebase->declaresClass(...)))
            // An operator or constructor protocol is handed a value of any type, and must ask what it is.
            ->reject(static fn (ExprMatch $match): bool => $match->isInDunder())
            ->reject(static fn (ExprMatch $match): bool => $match->isInFromSourceFactory())
            ->reject(static fn (ExprMatch $match): bool => $match->typeSwitchTranslatesEveryArm())
            ->get();
    }
}
