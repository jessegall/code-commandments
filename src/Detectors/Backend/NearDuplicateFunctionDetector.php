<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\NearDuplicateFunction;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;

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
     * Framework contract bases mapped to their per-subclass DECLARATION HOOKS — methods
     * each subclass must re-declare to state its own field contract (`rules()` for a
     * request's validation array; `schema()` for an MCP tool's input schema). Every
     * subclass's hook shares the same `['field' => …]` skeleton BY CONTRACT, so the
     * similarity is inherent — it can no more be parameterised into one shared method than
     * `rules()` can. These are the framework's own hook names, not a suffix heuristic.
     *
     * @var array<string, list<string>>
     */
    private const array CONTRACT_HOOKS = [
        'Illuminate\\Foundation\\Http\\FormRequest' => ['rules'],
        'Laravel\\Mcp\\Request' => ['rules'],
        'Laravel\\Mcp\\Server\\Tool' => ['rules', 'schema'],
    ];

    public function sin(): Sin
    {
        return new NearDuplicateFunction();
    }

    public function find(Codebase $codebase): array
    {
        $byShape = [];
        $exactCounts = [];

        foreach ($codebase->whereMethodDeclaration()->get() as $match) {
            if ($match->bodyNodeCount() < self::MIN_BODY_NODES) {
                continue;
            }

            if ($this->isContractDeclarationHook($codebase, $match)) {
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

    private function isContractDeclarationHook(Codebase $codebase, NodeMatch $match): bool
    {
        $method = $match->enclosingFunctionName();

        foreach (self::CONTRACT_HOOKS as $base => $hooks) {
            if (in_array($method, $hooks, true) && $codebase->extends($match->enclosingClassName(), $base)) {
                return true;
            }
        }

        return false;
    }
}
