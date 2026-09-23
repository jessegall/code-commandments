<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\ConstructorSideEffect;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A constructor that tells a collaborator it was handed to act and ignores the answer — the C# twin of the
 * Python constructor-side-effect rule.
 */
final class ConstructorSideEffectDetector implements Detector
{
    public function sin(): Sin
    {
        return new ConstructorSideEffect();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->is('InvocationExpression'))
            ->where(static fn (NodeMatch $match): bool => $match->isConstructorSideEffect())
            ->get();
    }
}
