<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\EnumValueMatch;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `match` over an enum's raw values at a call site — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\EnumValueMatchDetector}. Python has no type
 * to say the subject IS an enum, so the cases say it: every literal one is a value of one enum the
 * codebase declares.
 */
final class EnumValueMatchDetector implements Detector
{
    public function sin(): Sin
    {
        return new EnumValueMatch();
    }

    public function find(Codebase $codebase): array
    {
        $enums = $codebase->enums();

        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->isMatchOnEnumValue($enums))
            ->get();
    }
}
