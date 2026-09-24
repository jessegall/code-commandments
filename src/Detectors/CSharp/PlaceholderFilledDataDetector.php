<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\PlaceholderFilledData;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A record the codebase declares, built with a blank string in a required `string` slot — the C# twin of the
 * PHP and Python placeholder-filled-data rules. A type's own Null Object is blank on purpose, and a test fills
 * what it does not care about.
 */
final class PlaceholderFilledDataDetector implements Detector
{
    public function sin(): Sin
    {
        return new PlaceholderFilledData();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->is('ObjectCreationExpression', 'ImplicitObjectCreationExpression'))
            ->where(static fn (NodeMatch $match): bool => $codebase->fillsRecordWithBlank($match->node))
            ->reject(static fn (NodeMatch $match): bool => $match->isNullObjectOfItsType())
            ->reject(static fn (NodeMatch $match): bool => $match->module->isTest())
            ->get();
    }
}
