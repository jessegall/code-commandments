<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Sins\CSharp\NullForgiven;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * The null-forgiving `!` applied to what is declared nullable — a `T?` field, property, local,
 * parameter or return, as the compiler resolves it. `= null!` initialises a member a framework fills,
 * and names nothing nullable; test code may let a null fail the test. Neither is flagged.
 */
final class NullForgivenDetector implements Detector
{
    public function sin(): Sin
    {
        return new NullForgiven();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereExpression(static fn (Node $node): bool => $node->forgivesNull)
            ->reject(static fn (NodeMatch $match): bool => $match->module->isTest())
            ->get();
    }
}
