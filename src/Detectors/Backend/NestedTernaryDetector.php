<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\NestedTernary;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\NestedTernaryScribe;

/**
 * A nested / chained ternary — `$a ? $b : ($c ? $d : $e)` — folds a branching
 * decision into one unreadable expression where the operator precedence is a
 * trap. Spell it as a `match (true)`, or lift the decision into guard clauses.
 * Points at guard-clauses-and-flow.
 */
final class NestedTernaryDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new NestedTernary();
    }

    public function scribe(): string
    {
        return NestedTernaryScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isOutermostNestedTernary())
            ->get();
    }
}
