<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\NearDuplicateFunction;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Packages\Exemptions;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Tags\ContractMethod;

/**
 * Two-or-more functions/methods with the same SHAPE but not identical text — the
 * same control-flow skeleton differing only in variable names or literal values
 * (a type-2 clone). The redundant-method smell: each does the same thing to a
 * different field/string, begging to be one parameterised method. Groups that are
 * byte-identical are left to `DuplicateFunctionDetector`; this catches the near
 * misses it can't see. A 12-body-node floor skips trivial look-alikes. Excluded, as
 * in the exact detector: a pure manifest, a constructor, a sole-expression
 * descriptor/delegate (`return <expr>;` or one call statement), a guard accessor, and a
 * `@deprecated` declaration. Points at fix-at-the-source.
 */
final class NearDuplicateFunctionDetector implements Detector, RecurrenceDetector, Exemptable
{
    /**
     * Minimum body AST-node count to compare. Higher than the exact detector's
     * floor (12): a fuzzy, name-and-literal-blind match collides by coincidence far
     * more often at small sizes, so a near-duplicate must be a method of real
     * substance — not a one-line delegation or a short array that merely rhymes.
     */
    private const int MIN_BODY_NODES = 20;

    /**
     * The bucket a finding belongs to — its literal-blind SHAPE, the same fingerprint {@see find}
     * groups by. Declared rather than inherited from {@see RecurringPattern} because the rule here is
     * cross-bucket: a member with a byte-identical twin belongs to the exact detector instead, which
     * no per-bucket loop can express.
     */
    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $finding instanceof NodeMatch ? $finding->shapeHash() : null;
    }

    public function sin(): Sin
    {
        return new NearDuplicateFunction();
    }

    public function exemptions(): array
    {
        return [ContractMethod::class => []];
    }

    public function find(Codebase $codebase): array
    {
        $byShape = [];
        $exactCounts = [];

        foreach ($codebase->whereMethodDeclaration()->get() as $match) {
            if ($match->bodyNodeCount() < self::MIN_BODY_NODES) {
                continue;
            }

            // A pure manifest (`return [ … ];`) DECLARES a data shape, it doesn't compute one —
            // there's no control-flow skeleton to parameterise, so independent classes sharing the
            // shape (every node's `outputs()`, every request's `rules()`) is not duplication.
            if ($match->returnsArrayLiteralOnly()) {
                continue;
            }

            // A constructor is not a near-duplicate: its fix would be one shared method, but two DIFFERENT
            // classes can't share a constructor — each declares its own (assign params, forward to parent),
            // so a similar `__construct` is expected structure, not a redundant algorithm.
            if ($match->isConstructorDeclaration()) {
                continue;
            }

            // A sole `return <expr>;` is a DESCRIPTOR or a one-line delegate — no control-flow skeleton
            // to hoist. Two of them that "differ only in their literals" are differing only in DATA, and
            // every extraction merely relocates that data (issues #364, #366). The exact detector already
            // exempts this shape; a literal-BLIND match needs the guard more, not less.
            if ($match->isSoleReturnExpression() || $match->isSoleExpressionStatement()) {
                continue;
            }

            // A resolve-or-throw guard accessor is a language idiom, and a `@deprecated` declaration is a
            // frozen snapshot you never refactor toward — both exempt for the same reason as above.
            if ($match->isGuardedAccessor() || $match->isDeprecated()) {
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
        return Exemptions::has(ContractMethod::class, $codebase, $match->enclosingClassName(), $match->enclosingFunctionName());
    }
}
