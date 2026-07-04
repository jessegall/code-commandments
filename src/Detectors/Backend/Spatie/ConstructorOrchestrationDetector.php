<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\ConstructorOrchestrationScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ConstructorOrchestration;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A page object imperatively filling a public slot in its constructor. Self-contained projections
 * belong in a `#[Computed]` property hook's `get` method, not the assembly-line constructor. Rejects
 * deferred/dependent/conditional/multi-step assignments that cannot safely become hooks.
 */
final class ConstructorOrchestrationDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new ConstructorOrchestration();
    }

    public function scribe(): string
    {
        return ConstructorOrchestrationScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereAssign()
            ->where(static fn (AstNode $node): bool => $node->assignsThisProperty())
            ->where(static fn (AstNode $node): bool => $node->enclosingFunctionName() === '__construct')
            ->where(static fn (SpatieDataNode $node): bool => $node->isPageObject())
            ->where(static fn (SpatieDataNode $node): bool => $node->assignedPropertyIsPublicSlot())
            ->reject(static fn (SpatieDataNode $node): bool => $node->assignmentRhsIsDeferred())
            ->reject(static fn (SpatieDataNode $node): bool => $node->assignedSlotTypeIsDeferred())
            ->reject(static fn (AstNode $node): bool => $node->assignmentReferencesLocalVariable())
            ->reject(static fn (AstNode $node): bool => $node->isWithinBranch())
            ->reject(static fn (SpatieDataNode $node): bool => $node->propertyAssignedMoreThanOnce())
            ->get();
    }
}
