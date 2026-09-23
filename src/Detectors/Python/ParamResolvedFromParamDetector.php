<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Py\ParamResolution;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\ParamResolvedFromParam;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A function that unpacks its target from a container parameter — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ParamResolvedFromParamDetector}: it should take the
 * resolved object, not the container plus a key.
 */
final class ParamResolvedFromParamDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new ParamResolvedFromParam();
    }

    public function find(Codebase $codebase): array
    {
        $resolution = new ParamResolution($codebase);

        return $codebase
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->node instanceof FunctionDef && $resolution->unpacksTargetFromContainerParam($match->node, $match->module))
            ->get();
    }
}
