<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Packages\AppliesExemptions;
use JesseGall\CodeCommandments\Packages\ExemptBy;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Tags\ArrayReturning;
use JesseGall\CodeCommandments\Packages\Tags\ContractMethod;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects multi-field string-keyed array returns (bags for value objects). Exempt: contract methods,
 * self-serializers, JSON schemas, method overrides, and SHAPED-array returns (`@return array{…}` — a
 * typed, statically-checkable struct, not a loose bag). Also exempt is a SCRIPT-SCOPE return — a
 * `config/*.php` or manifest file whose whole purpose is to hand back a keyed map. There is no method
 * there whose contract could be a value object. Points at value-objects.
 */
final class ArrayReturnBagDetector implements Detector, Exemptable
{
    use AppliesExemptions;

    public function sin(): Sin
    {
        return new ArrayReturnBag();
    }

    public function exemptions(): array
    {
        return [
            ArrayReturning::class => [ExemptBy::EnclosingClass],
            ContractMethod::class => [ExemptBy::EnclosingMethod],
        ];
    }

    public function find(Codebase $codebase): array
    {
        return $this->exempt($codebase
            ->where(static fn (AstNode $node): bool => $node->stringKeyCount() >= 2)
            ->where(static fn (AstNode $node): bool => $node->isReturnedValue())
            ->reject(static fn (AstNode $node): bool => $node->enclosingFunction() === null)
            ->reject(static fn (AstNode $node): bool => $node->hasNestedArrayValue())
            ->reject(static fn (AstNode $node): bool => $node->looksLikeJsonSchema())
            ->reject(static fn (AstNode $node): bool => $node->isSelfProjectionArray())
            ->reject(static fn (AstNode $node): bool => $node->enclosingFunctionReturnsShapedArray())
            ->reject(static fn (AstNode $node): bool => $codebase->overridesMethod($node->enclosingClassName(), $node->enclosingFunctionName() ?? ''))
            ->get(), $codebase);
    }
}
