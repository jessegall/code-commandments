<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Two-or-more functions/methods with the same SHAPE but not identical text — the
 * same control-flow skeleton differing only in variable names or literal values
 * (a type-2 clone). The redundant-method smell: each does the same thing to a
 * different field/string, begging to be one parameterised method. Groups that are
 * byte-identical are left to `DuplicateFunctionDetector`; this catches the near
 * misses it can't see. A 12-body-node floor skips trivial look-alikes. Points at
 * fix-at-the-source.
 */
final class NearDuplicateFunctionDetector implements Detector
{
    /**
     * Minimum body AST-node count to compare. Higher than the exact detector's
     * floor (12): a fuzzy, name-and-literal-blind match collides by coincidence far
     * more often at small sizes, so a near-duplicate must be a method of real
     * substance — not a one-line delegation or a short array that merely rhymes.
     */
    private const int MIN_BODY_NODES = 20;

    /**
     * Framework request bases whose `rules()` is a validation-array hook: every
     * subclass's `rules()` shares the same `['field' => [...]]` skeleton by contract,
     * so structural similarity is inherent, not duplication to extract.
     */
    private const array REQUEST_BASES = [
        'Illuminate\\Foundation\\Http\\FormRequest',
        'Laravel\\Mcp\\Request',
        'Laravel\\Mcp\\Server\\Tool',
    ];

    public function skill(): string
    {
        return 'backend/fix-at-the-source';
    }

    public function find(Codebase $codebase): array
    {
        $byShape = [];
        $exactCounts = [];

        foreach ($codebase->whereMethodDeclaration()->get() as $match) {
            if ($match->bodyNodeCount() < self::MIN_BODY_NODES) {
                continue;
            }

            if ($this->isRequestRules($codebase, $match)) {
                continue;
            }

            $byShape[$match->shapeHash()][] = $match;
            $exactCounts[$match->structuralHash()] = ($exactCounts[$match->structuralHash()] ?? 0) + 1;
        }

        $findings = [];

        foreach ($byShape as $matches) {
            if (count($matches) < 2) {
                continue;
            }

            // Flag only members WITHOUT a byte-identical twin — exact duplicates are
            // DuplicateFunctionDetector's job, so the two never report the same line.
            foreach ($matches as $match) {
                if (($exactCounts[$match->structuralHash()] ?? 0) === 1) {
                    $findings[] = $match;
                }
            }
        }

        return $findings;
    }

    private function isRequestRules(Codebase $codebase, NodeMatch $match): bool
    {
        if ($match->enclosingFunctionName() !== 'rules') {
            return false;
        }

        foreach (self::REQUEST_BASES as $base) {
            if ($codebase->extends($match->enclosingClassName(), $base)) {
                return true;
            }
        }

        return false;
    }
}
