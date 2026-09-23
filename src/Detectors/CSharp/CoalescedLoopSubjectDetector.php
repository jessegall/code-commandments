<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\CoalescedLoopSubject;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A loop over a collection defaulted to empty in its own header — the C# twin of the PHP and Python
 * coalesced-loop-subject rules.
 */
final class CoalescedLoopSubjectDetector implements Detector
{
    public function sin(): Sin
    {
        return new CoalescedLoopSubject();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node): bool => $node->is('ForEachStatement'))
            ->where(static fn (NodeMatch $loop): bool => ($loop->node->expressions()[0] ?? null)?->is('CoalesceExpression') === true)
            ->where(static fn (NodeMatch $loop): bool => $loop->node->expressions()[0]->fallback()->isSomeAnd(static fn (Node $fallback): bool => $fallback->isEmptyCollection()))
            ->get();
    }
}
