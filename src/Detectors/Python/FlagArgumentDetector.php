<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\FlagArgument;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A function that is nothing but a choice on one of its own parameters — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\FlagArgumentDetector}.
 */
final class FlagArgumentDetector implements Detector
{
    public function sin(): Sin
    {
        return new FlagArgument();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereFunction()
            // A constructor's parameters are the object's own fields being born, never a switch.
            ->reject(static fn (NodeMatch $match): bool => $match->isConstructorDeclaration())
            ->where(static fn (NodeMatch $match): bool => $match->node instanceof FunctionDef && $match->node->switchesEntirelyOnAParameter())
            ->get();
    }
}
