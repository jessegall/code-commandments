<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\InArrayMirrorsEnum;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A membership test against strings written right there — `Contains` over an inline list, or an `is … or …`
 * pattern — that all name members of one enum the codebase declares: the C# twin of the PHP
 * in-array-mirrors-enum rule.
 */
final class InArrayMirrorsEnumDetector implements Detector
{
    public function sin(): Sin
    {
        return new InArrayMirrorsEnum();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->isCall() || $expression->is('IsPatternExpression'))
            ->where(static fn (NodeMatch $match): bool => $codebase->enumMirroredBy($match->node->membershipLiterals()))
            ->get();
    }
}
