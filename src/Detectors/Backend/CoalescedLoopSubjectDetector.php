<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\CoalescedLoopSubject;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `foreach` that decides in its own header whether it was HANDED anything to iterate —
 * `foreach ($below[$id] ?? [] as $child)`, `$below` being a parameter. Whether the caller passed a
 * collection is a precondition, and preconditions belong at the door, not buried in a loop header.
 * Rooted at a parameter deliberately ({@see AstNode::reachesIntoParameter}): coalescing your OWN
 * state (`$this->listeners[$id] ?? []`) is a sparse registry answering "nobody", its ordinary
 * answer, and a normalised call result (`glob(…) ?: []`) is absence fixed AT its source. Unlike
 * {@see ManufacturedFakeFillDetector} this asks nothing about the empty collection as a VALUE
 * (an honest one — #398), only about where the absence gets decided.
 */
final class CoalescedLoopSubjectDetector implements Detector
{
    public function sin(): Sin
    {
        return new CoalescedLoopSubject();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isLoopSubject())
            ->where(static fn (AstNode $node): bool => $node->fallsBackToEmptyCollection())
            ->where(static fn (AstNode $node): bool => $node->fallbackSubject()->reachesIntoParameter())
            ->get();
    }
}
