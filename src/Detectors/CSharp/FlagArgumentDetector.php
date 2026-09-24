<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\FlagArgument;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A method whose whole body is a two-way branch on one of its `bool` parameters — the C# twin of the PHP and
 * Python flag-argument rules. A nullable parameter branched on is C#'s way of handling an optional input once,
 * and is left alone; an override keeps the signature its contract set, and a test's helpers are its own.
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
            ->whereNode(static fn (Node $method): bool => $method->is('MethodDeclaration', 'LocalFunctionStatement'))
            ->where(static fn (NodeMatch $match): bool => $match->node->switchesOnAFlag())
            ->reject(static fn (NodeMatch $match): bool => $match->isOverride())
            ->reject(static fn (NodeMatch $match): bool => $match->module->isTest())
            ->get();
    }
}
