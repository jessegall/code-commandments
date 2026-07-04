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
 * A page object filling a PUBLIC slot imperatively in its constructor — `$this->docks =
 * $this->dockProjector->project();` — where a `#[Computed]` property hook would declare the slot
 * next to how it is projected. The fat assembly-line constructor is the smell; a self-contained
 * projection belongs in a `get` hook. Points at page-objects.
 *
 * The rejects keep it to the assignments that can SAFELY become a hook:
 *  - a deferred slot stays — whether the right-hand side is a `Lazy::…()`/`new DeferProp` or the slot's
 *    own type is `Lazy|…`/`…Prop` (a factory like `Table::asClosureLazy(): Lazy`); hoisting it into an
 *    eager `get` would destroy the deferral;
 *  - a right-hand side reading a constructor local/param stays — a `get` hook sees only `$this`, so
 *    an intermediate unwrapped once for reuse (the shared-`$menus` case) is legitimately imperative;
 *  - a branch-guarded assignment stays — it isn't a straight-line slot fill; and
 *  - a slot written more than once stays — it's built up in steps, not one expression.
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
