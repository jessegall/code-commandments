<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Two-or-more functions/methods with an identical AST — the same code copy-pasted,
 * down to a formatting-blind structural hash (spacing, newlines, and comments are
 * ignored; only real code differences count). Copy-paste is one decision living in
 * many places: hoist it to a shared method, trait, or base and call it once.
 * Trivial declarations (tiny getters, empty stubs) are below the size floor so
 * incidental likeness isn't flagged. Points at fix-at-the-source.
 */
final class DuplicateFunctionDetector implements Detector
{
    /**
     * Minimum body AST-node count for a declaration to be worth comparing — below
     * this, identical short methods are ordinary, not copy-paste.
     */
    private const int MIN_BODY_NODES = 12;

    public function sin(): Sin
    {
        return new DuplicateFunction();
    }

    public function find(Codebase $codebase): array
    {
        $byHash = [];

        foreach ($codebase->whereMethodDeclaration()->get() as $match) {
            if ($match->bodyNodeCount() >= self::MIN_BODY_NODES) {
                $byHash[$match->structuralHash()][] = $match;
            }
        }

        $findings = [];

        foreach ($byHash as $matches) {
            if (count($matches) >= 2) {
                array_push($findings, ...$matches);
            }
        }

        return $findings;
    }
}
