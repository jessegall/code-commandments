<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\ArrayReturnBag;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A dictionary built with two or more fixed string keys, handed back by a member — the C# twin of the PHP
 * array-return-bag rule. An override or an interface member returns the shape its contract declared, so the
 * dictionary is the contract's; a test builds its input in the shape the code under test takes.
 */
final class ArrayReturnBagDetector implements Detector
{
    public function sin(): Sin
    {
        return new ArrayReturnBag();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Node $expression): bool => count(array_unique($expression->literalKeys())) >= 2)
            ->where(static fn (NodeMatch $match): bool => $match->isReturned())
            ->reject(static fn (NodeMatch $match): bool => $match->isWithinOverride())
            ->reject(static fn (NodeMatch $match): bool => $match->module->isTest())
            ->get();
    }
}
