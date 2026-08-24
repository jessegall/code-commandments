<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\NullableRegistryLookup;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects registries returning null on miss (`$this->items[$key] ?? null`) instead of throwing. A lookup
 * into a parameter map is exempt, and so is one in a method ANSWERING an inherited declaration: there the
 * `?T` is the contract the class was handed, not a store choosing to shrug. Points at role-vocabulary.
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
            ->reject(fn (AstNode $node): bool => $codebase->overridesMethod($node->enclosingClassName(), $node->enclosingFunctionName()))
            ->get();
    }
}
