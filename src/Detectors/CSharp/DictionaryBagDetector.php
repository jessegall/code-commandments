<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Sins\CSharp\DictionaryBag;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A string-keyed dictionary or JSON object read by keys written in the source — the C# twin of Python's
 * dict-bag rule, and like it, found both where the key is read and where a lookup helper is handed the
 * key — a dictionary the reading code owns, a parameter or a local. Reading the input by name inside
 * the type that builds itself from it, or inside the `new` a parser builds a record with, is the edge;
 * a dictionary a contract hands an override, a bag
 * reached through another object, a write, and test code checking a wire shape are not this sin.
 */
final class DictionaryBagDetector implements Detector
{
    public function sin(): Sin
    {
        return new DictionaryBag();
    }

    public function find(Codebase $codebase): array
    {
        $direct = $codebase
            ->whereExpression(static fn (Node $node): bool => $node->isStringKeyRead())
            ->where(static fn (NodeMatch $match): bool => $match->isReadingOwnDictionary())
            ->reject(static fn (NodeMatch $match): bool => $match->isAssignedTo())
            ->reject(static fn (NodeMatch $match): bool => $match->isWithinOverride())
            ->reject(static fn (NodeMatch $match): bool => $match->isWithinNamedConstructor())
            ->reject(static fn (NodeMatch $match): bool => $match->isBuildingAnObject())
            ->reject(static fn (NodeMatch $match): bool => $match->module->isTest())
            ->get();

        $throughHelper = $codebase
            ->whereCall()
            ->where(static fn (NodeMatch $match): bool => $codebase->passesLiteralKey($match->node))
            ->reject(static fn (NodeMatch $match): bool => $match->isWithinNamedConstructor())
            ->reject(static fn (NodeMatch $match): bool => $match->isBuildingAnObject())
            ->reject(static fn (NodeMatch $match): bool => $match->module->isTest())
            ->get();

        return [...$direct, ...$throughHelper];
    }
}
