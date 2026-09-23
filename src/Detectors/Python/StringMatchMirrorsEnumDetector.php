<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\StringMatchMirrorsEnum;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `match` on loose strings that are one declared enum's values — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\StringMatchMirrorsEnumDetector}. A match on
 * `x.value` is {@see EnumValueMatchDetector}'s, and numbers are left out: small integers match some
 * `IntEnum` by chance.
 */
final class StringMatchMirrorsEnumDetector implements Detector
{
    public function sin(): Sin
    {
        return new StringMatchMirrorsEnum();
    }

    public function find(Codebase $codebase): array
    {
        $enums = $codebase->enums();

        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->isStringMatchMirroringEnum($enums))
            ->get();
    }
}
