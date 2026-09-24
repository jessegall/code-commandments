<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\MemberOutOfOrder;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A constant declared below a type's per-object state — the C# twin of the PHP and Python
 * member-out-of-order rules.
 */
final class MemberOutOfOrderDetector implements Detector
{
    public function sin(): Sin
    {
        return new MemberOutOfOrder();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $member): bool => $member->isConstantMember())
            ->where(static fn (NodeMatch $match): bool => $match->isMemberOutOfOrder())
            ->get();
    }
}
