<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\DeepNesting;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * An `if` nested three-deep — a pyramid of conditions. The arrow-shaped code is
 * preconditions that want to be guard clauses at the top, or a branch that wants
 * to be its own method. Flatten it. Points at guard-clauses-and-flow.
 */
final class DeepNestingDetector implements Detector
{
    public function sin(): Sin
    {
        return new DeepNesting();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isDeeplyNestedIf())
            ->get();
    }
}
