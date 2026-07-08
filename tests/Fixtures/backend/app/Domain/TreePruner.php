<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedTypeGuard;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * CROSS-CLASS partner of `TreeGuard`: the exact `$node instanceof Leaf && $node->parent instanceof Branch`
 * narrowing, copied into a different class in a different file. Proves the type-guard fingerprint buckets
 * across the whole codebase, not just within one class. The fix is one shared named predicate.
 */
final class TreePruner
{
    #[Sinful(RepeatedTypeGuard::class)]
    public function keep($node): bool
    {
        if ($node instanceof Leaf && $node->parent instanceof Branch) {
            return false;
        }

        return true;
    }

    public function trimmed(int $before, int $after): int
    {
        return max($before - $after, 0);
    }
}
