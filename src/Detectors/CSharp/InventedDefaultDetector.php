<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Sins\CSharp\InventedDefault;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A missing value papered over with an invented `""`, `0` or `false` — the C# twin of Python's
 * invented-default rule, in its hardened shape. Two places it hides: a fallback handed straight to a
 * call as its argument (`F(x ?? "")`), and a member that answers a dictionary lookup's miss with one.
 * A fallback that is a real choice (`?? "EUR"`) is a default, not an invention, and is left alone.
 */
final class InventedDefaultDetector implements Detector
{
    public function sin(): Sin
    {
        return new InventedDefault();
    }

    public function find(Codebase $codebase): array
    {
        $filled = $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->fallback()->isSomeAnd(static fn (Node $fallback): bool => $fallback->isEmptyScalar()))
            ->where(static fn (NodeMatch $match): bool => $match->fillsArgument())
            ->get();

        $answered = $codebase
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->isInventingOnMiss())
            ->get();

        return [...$filled, ...$answered];
    }
}
