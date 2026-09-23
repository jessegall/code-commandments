<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\NonCountingFor;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `for` whose step assigns the next item rather than moving a counter — the C# twin of the PHP
 * non-counting-for rule.
 */
final class NonCountingForDetector implements Detector
{
    public function sin(): Sin
    {
        return new NonCountingFor();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $statement): bool => $statement->isNonCountingFor())
            ->get();
    }
}
