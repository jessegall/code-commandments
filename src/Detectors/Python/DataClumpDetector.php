<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\DataClump;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * The same three-or-more scalar parameters threaded through functions of two or more classes or
 * modules — the Python twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\DataClumpDetector}. An `__init__` or a named
 * constructor taking them is the value being born, not a clump.
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
     * The clump a finding belongs to — its sorted scalar signature.
     */
    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $finding instanceof NodeMatch && $finding->node instanceof FunctionDef ? implode(', ', $finding->node->valueParamSignature()) : null;
    }

    public function find(Codebase $codebase): array
    {
        $byClump = [];

        $candidates = $codebase
            ->whereFunction()
            ->where(static fn (NodeMatch $match): bool => $match->node instanceof FunctionDef && $match->node->valueParamSignature() !== [])
            ->reject(static fn (NodeMatch $match): bool => $match->isConstructorDeclaration())
            ->reject(static fn (NodeMatch $match): bool => $match->node instanceof FunctionDef && $match->node->isNamedConstructor())
            ->get();

        foreach ($candidates as $match) {
            $byClump[$this->groupKey($match, $codebase)][] = $match;
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
