<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\ConstClassEnum;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A class of nothing but `const` strings or numbers, one of which is compared as a case somewhere — the C#
 * twin of the PHP const-class-enum rule. Constants only ever handed to an API as names (a policy, a scheme,
 * a header) are a list of keys, not a closed set anything dispatches on.
 */
final class ConstClassEnumDetector implements Detector
{
    public function sin(): Sin
    {
        return new ConstClassEnum();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $type): bool => $type->isConstClassEnum())
            ->where(static fn (NodeMatch $match): bool => $codebase->comparesAsACase((string) $match->node->symbol))
            ->get();
    }
}
