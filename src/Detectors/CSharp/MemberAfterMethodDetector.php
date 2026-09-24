<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\MemberAfterMethod;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * State declared below a constructor or a method — the C# twin of the PHP and Python member-after-method rules.
 */
final class MemberAfterMethodDetector implements Detector
{
    public function sin(): Sin
    {
        return new MemberAfterMethod();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $member): bool => $member->isStateMember())
            ->where(static fn (NodeMatch $match): bool => $match->isMemberAfterMethod())
            ->get();
    }
}
