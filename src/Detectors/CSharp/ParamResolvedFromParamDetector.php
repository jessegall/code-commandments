<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\ParamResolvedFromParam;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A method that unpacks its target from a container parameter — the twin of the backend's and Python's
 * param-resolved-from-param rules: it should take the resolved object, not the container plus a key.
 */
final class ParamResolvedFromParamDetector implements Detector
{
    public function sin(): Sin
    {
        return new ParamResolvedFromParam();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (NodeMatch $match): bool => $match->unpacksTargetFromContainerParam($codebase))
            ->get();
    }
}
