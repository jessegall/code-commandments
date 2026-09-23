<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\NestedTernary;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A conditional expression nested in another's branch — the C# twin of the PHP nested-ternary rule.
 */
final class NestedTernaryDetector implements Detector
{
    public function sin(): Sin
    {
        return new NestedTernary();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->isNestedConditional())
            ->reject(static fn (NodeMatch $match): bool => $match->isConditionalBranch())
            ->get();
    }
}
