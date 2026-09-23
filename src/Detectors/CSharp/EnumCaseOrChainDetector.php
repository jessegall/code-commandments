<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\EnumCaseOrChain;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Two or more cases of one enum the codebase declares, tested together by `||` or an `is … or …` pattern —
 * the C# twin of the PHP enum-case-or-chain rule.
 */
final class EnumCaseOrChainDetector implements Detector
{
    public function sin(): Sin
    {
        return new EnumCaseOrChain();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node): bool => $node->is('LogicalOrExpression', 'OrPattern'))
            ->where(static fn (NodeMatch $match): bool => $match->isGroupTestRoot())
            ->where(static fn (NodeMatch $match): bool => array_any($match->node->enumsTestedAsAGroup(), $codebase->declaresEnum(...)))
            ->get();
    }
}
