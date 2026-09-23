<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\CSharp\DataClump;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * The same three or more value parameters — by type and name — declared by members of two or more types:
 * one clump of data threaded through the code instead of named. The C# twin of Python's data-clump rule.
 * A constructor sets its own state; an override or interface implementation repeats its contract's
 * signature, as the compiler resolves it; a static factory building its own type takes what the type is
 * made of. None of them is compared.
 */
final class DataClumpDetector implements Detector, RecurrenceDetector
{
    /**
     * How many distinct owners a signature must span before it is a clump rather than one wide signature.
     */
    private const int OWNERS = 2;

    public function sin(): Sin
    {
        return new DataClump();
    }

    /**
     * The clump a finding belongs to — its sorted value signature.
     */
    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $finding instanceof NodeMatch && $finding->node->valueParamSignature() !== [] ? implode(', ', $finding->node->valueParamSignature()) : null;
    }

    public function find(Codebase $codebase): array
    {
        $candidates = $codebase
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->node->valueParamSignature() !== [])
            ->reject(static fn (NodeMatch $match): bool => $match->isOverride())
            ->reject(static fn (NodeMatch $match): bool => $match->isNamedConstructor())
            ->get();

        $byClump = [];

        foreach ($candidates as $match) {
            $byClump[implode(', ', $match->node->valueParamSignature())][] = $match;
        }

        $findings = [];

        foreach ($byClump as $matches) {
            if (count(array_unique(array_map(static fn (NodeMatch $match): string => $match->owner(), $matches))) >= self::OWNERS) {
                array_push($findings, ...$matches);
            }
        }

        return $findings;
    }
}
