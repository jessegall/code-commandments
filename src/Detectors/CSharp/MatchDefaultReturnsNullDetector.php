<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\MatchDefaultReturnsNull;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A switch over an enum the codebase declares that names every member, its fallback answering nothing —
 * the C# twin of the PHP match-default-returns-null rule. With every member named, the fallback is reached
 * only by a value no member names. A `Try…` method's `false` is its failure report, not a silence.
 */
final class MatchDefaultReturnsNullDetector implements Detector
{
    public function sin(): Sin
    {
        return new MatchDefaultReturnsNull();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node): bool => $node->is('SwitchExpression', 'SwitchStatement'))
            ->where(static fn (NodeMatch $match): bool => $match->node->fallbackValue()->isSomeAnd(static fn (Node $value): bool => $value->isAbsenceValue()))
            ->where(static fn (NodeMatch $match): bool => $codebase->namesEveryMember($match->node))
            ->reject(static fn (NodeMatch $match): bool => $match->node->fallbackValue()->isSomeAnd(static fn (Node $value): bool => $value->is('FalseLiteralExpression')) && $match->isWithinTryMethod())
            ->get();
    }
}
