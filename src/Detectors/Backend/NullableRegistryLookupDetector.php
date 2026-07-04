<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\NullableRegistryLookup;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects registries returning null on miss (`$this->items[$key] ?? null`) instead of throwing.
 * A lookup into a parameter map is exempt. Points at role-vocabulary.
 */
final class NullableRegistryLookupDetector implements Detector
{
    public function sin(): Sin
    {
        return new NullableRegistryLookup();
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
