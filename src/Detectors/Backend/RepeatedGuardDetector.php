<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Sins\Backend\RepeatedGuard;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a compound boolean guard (≥2 substantive conjuncts, at least one NOT a bare `instanceof` — pure-type
 * chains are the repeated-type-guard's domain) that recurs in ≥2 places. Buckets by a CANONICAL fingerprint
 * that ignores conjunct order AND inlines single-assignment locals, so the same check counts however it is
 * spelled: `$obj->some && $other->some`, `$other->some && $obj->some`, and `$objSome && $otherSome` (with
 * `$objSome = $obj->some`) all collide. The fix is a named predicate; a one-off guard is fine.
 */
final class RepeatedGuardDetector extends RecurringPattern
{
    public function sin(): Sin
    {
        return new RepeatedGuard();
    }

    protected function candidates(Codebase $codebase): array
    {
        return $codebase->where(static fn (AstNode $node): bool => $node->isSubstantiveGuard())->get();
    }

    public function groupKey(NodeMatch $finding, Codebase $codebase): ?string
    {
        return $finding->canonicalGuardHash();
    }
}
