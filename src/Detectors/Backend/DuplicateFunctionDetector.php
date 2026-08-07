<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\DuplicateFunction;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;

/**
 * Two-or-more functions/methods with an identical AST — the same code copy-pasted,
 * down to a formatting-blind structural hash (spacing, newlines, and comments are
 * ignored; only real code differences count). Copy-paste is one decision living in
 * many places: hoist it to a shared method, trait, or base and call it once.
 * Trivial declarations (tiny getters, empty stubs) are below the size floor; a
 * resolve-or-throw guard accessor (a language idiom, not shared logic), a sole
 * `return <expr>;` descriptor/delegate (no procedure to hoist), and a `@deprecated`
 * declaration (a frozen snapshot you never refactor toward) are excluded, so incidental
 * likeness across independent classes isn't flagged. Points at fix-at-the-source.
 */
final class DuplicateFunctionDetector extends RecurringPattern
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

    protected function candidates(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (NodeMatch $match): bool => $match->bodyNodeCount() >= self::MIN_BODY_NODES)
            ->reject(static fn (NodeMatch $match): bool => $match->isGuardedAccessor())
            ->reject(static fn (NodeMatch $match): bool => $match->isSoleReturnExpression())
            ->reject(static fn (NodeMatch $match): bool => $match->isDeprecated())
            ->get();
    }

    protected function fingerprint(NodeMatch $finding, Codebase $codebase): ?string
    {
        return $finding->structuralHash();
    }
}
