<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Packages\Exemptions;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Tags\ArrayReturning;
use JesseGall\CodeCommandments\Packages\Tags\ContractMethod;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects multi-field string-keyed array returns (bags for value objects). Exempt: contract methods,
 * self-serializers, JSON schemas, method overrides, and SHAPED-array returns (`@return array{…}` — a
 * typed, statically-checkable struct, not a loose bag). Points at value-objects.
 */
final class ArrayReturnBagDetector implements Detector, Exemptable
{
    public function sin(): Sin
    {
        return new ArrayReturnBag();
    }

    public function exemptions(): array
    {
        return [ArrayReturning::class, ContractMethod::class];
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->stringKeyCount() >= 2)
            ->where(static fn (AstNode $node): bool => $node->isReturnedValue())
            ->reject(static fn (AstNode $node): bool => $node->hasNestedArrayValue())
            ->reject(static fn (AstNode $node): bool => $node->looksLikeJsonSchema())
            ->reject(static fn (AstNode $node): bool => $node->isSelfProjectionArray())
            ->reject(static fn (AstNode $node): bool => $node->enclosingFunctionReturnsShapedArray())
            ->reject(static fn (AstNode $node): bool => Exemptions::has(ArrayReturning::class, $codebase, $node->enclosingClassName()))
            ->reject(static fn (AstNode $node): bool => Exemptions::has(ContractMethod::class, $codebase, $node->enclosingClassName(), $node->enclosingFunctionName()))
            ->reject(static fn (AstNode $node): bool => $codebase->overridesMethod($node->enclosingClassName(), $node->enclosingFunctionName() ?? ''))
            ->get();
    }
}
