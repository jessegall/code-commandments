<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\RepeatedTypeGuard;
use JesseGall\CodeCommandments\Testing\Sinful;

/*
 * CROSS-CLASS half of a repeated type-guard: `$node instanceof Leaf && $node->parent instanceof Branch`
 * lives here AND, verbatim, in `TreePruner` — two DIFFERENT classes in two DIFFERENT files. The fingerprint
 * buckets the copy across the whole codebase, so a type-narrowing guard copied between classes is caught.
 */
final class TreeGuard
{
    #[Sinful(RepeatedTypeGuard::class)]
    public function graftable($node): bool
    {
        return $node instanceof Leaf && $node->parent instanceof Branch;
    }

    public function depth($node, int $from): int
    {
        return $from + 1;
    }
}
