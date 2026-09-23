<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\WrappingWithoutCause;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A new exception thrown from a catch without the caught one as its inner exception — the C# twin of the
 * PHP and Python wrapping-without-cause rules.
 */
final class WrappingWithoutCauseDetector implements Detector
{
    public function sin(): Sin
    {
        return new WrappingWithoutCause();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node): bool => $node->is('ThrowStatement', 'ThrowExpression'))
            ->where(static fn (NodeMatch $match): bool => $match->isWrappingWithoutCause())
            ->get();
    }
}
