<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\IfElseLadder;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * An `if`/`elseif` ladder of four-plus branches — a chain of conditions doing the
 * job of a `match`, a method on the type, or polymorphic dispatch. Each branch
 * hides the next; the shape says "this is a closed set being decided by hand".
 * Points at guard-clauses-and-flow.
 */
final class IfElseLadderDetector implements Detector
{
    public function sin(): Sin
    {
        return new IfElseLadder();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isIfElseLadder())
            ->get();
    }
}
