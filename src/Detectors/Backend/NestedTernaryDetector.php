<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A nested / chained ternary — `$a ? $b : ($c ? $d : $e)` — folds a branching
 * decision into one unreadable expression where the operator precedence is a
 * trap. Spell it as a `match (true)`, or lift the decision into guard clauses.
 * Points at guard-clauses-and-flow.
 */
final class NestedTernaryDetector implements Detector
{
    public function skill(): string
    {
        return 'guard-clauses-and-flow';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isOutermostNestedTernary())
            ->get();
    }
}
