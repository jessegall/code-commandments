<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\MutableStaticState;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A static field written from a method — the C# twin of the Python mutable-static-state rule.
 */
final class MutableStaticStateDetector implements Detector
{
    public function sin(): Sin
    {
        return new MutableStaticState();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->children !== [])
            ->where(static fn (NodeMatch $match): bool => $match->isWritingStaticState())
            ->get();
    }
}
