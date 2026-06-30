<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A class's own keyed store handing back `null` on a miss — `return
 * $this->items[$key] ?? null`. That is a registry that refuses to own its role:
 * a lookup that can't resolve should say so by throwing (resolve-or-throw), not
 * push an `?object` onto every caller to re-check. Points at role-vocabulary.
 *
 * A lookup into a *parameter* map (`$attributes[$key] ?? null`) is a caller-owned
 * dynamic bag, not a registry the class owns, so it's left alone.
 */
final class NullableRegistryLookupDetector implements Detector
{
    public function skill(): string
    {
        return 'backend/role-vocabulary';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isCoalesce())
            ->where(static fn (AstNode $node): bool => $node->isReturnedValue())
            ->where(static fn (AstNode $node): bool => $node->coalesceRight()->isNull())
            ->where(static fn (AstNode $node): bool => $node->coalesceLeft()->isOwnedKeyedLookup())
            ->get();
    }
}
