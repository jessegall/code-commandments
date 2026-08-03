<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\NonCountingFor;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `for` that is not counting — its step ASSIGNS the next thing rather than advancing a counter,
 * as in `for ($one = $r; $one !== null; $one = $one instanceof Traceable ? $one->getAbove() : null)`.
 * The form promises init-test-step over an induction variable, so a reader takes the header in at a
 * glance; the moment the step has to work out where to go, all three clauses become a puzzle — and
 * the header is where the branching, the sentinel and the bound all end up hidden. A walk is a
 * `while` over a cursor, or, better, a sequence the walked type hands out itself.
 */
final class NonCountingForDetector implements Detector
{
    public function sin(): Sin
    {
        return new NonCountingFor();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isNonCountingFor())
            ->get();
    }
}
