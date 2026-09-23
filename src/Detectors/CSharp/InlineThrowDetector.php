<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\InlineThrow;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `?? throw` buried in a call's argument or receiver — the C# twin of the PHP inline-throw rule. The
 * assignment guard `x = y ?? throw …` is the language's own idiom and is left alone.
 */
final class InlineThrowDetector implements Detector
{
    public function sin(): Sin
    {
        return new InlineThrow();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Node $expression): bool => $expression->is('CoalesceExpression'))
            ->where(static fn (NodeMatch $match): bool => $match->isBuriedThrow())
            ->get();
    }
}
