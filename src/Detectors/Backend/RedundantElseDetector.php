<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\RedundantElse;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * An `else` after an `if` branch that already exits (`return`/`throw`/`continue`/
 * `break`). The `else` is dead weight: drop it and let the happy path continue
 * unindented at the top level — the guard has already handled the other case.
 * Points at guard-clauses-and-flow.
 */
final class RedundantElseDetector implements Detector
{
    public function sin(): Sin
    {
        return new RedundantElse();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->hasRedundantElse())
            ->get();
    }
}
