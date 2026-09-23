<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\MemberAfterMethod;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Class-level state written below a method — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\MemberAfterMethodDetector}.
 */
final class MemberAfterMethodDetector implements Detector
{
    public function sin(): Sin
    {
        return new MemberAfterMethod();
    }

    public function find(Codebase $codebase): array
    {
        $enums = $codebase->enums();

        return $codebase
            ->whereStatement()
            ->where(static fn (NodeMatch $match): bool => $match->isMemberAfterMethod($enums))
            ->get();
    }
}
