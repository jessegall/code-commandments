<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Sins\CSharp\StringMirrorsEnum;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `switch` or an `if` ladder dispatching on two or more strings that all name members of one enum the
 * codebase declares — the C# twin of the backend's string-match-mirrors-enum rule. The names compare
 * case-blind, as a wire format spells a member in any case.
 */
final class StringMirrorsEnumDetector implements Detector
{
    public function sin(): Sin
    {
        return new StringMirrorsEnum();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $node): bool => $node->is('SwitchStatement', 'SwitchExpression', 'IfStatement'))
            ->where(static fn (NodeMatch $match): bool => $codebase->enumMirroredBy($match->comparedLiterals()))
            ->get();
    }
}
