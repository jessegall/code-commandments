<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\SwallowCatch;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A `catch` that swallows the failure into absence — an empty body, or whose only
 * effect is `return null/false/[]`. The error vanishes silently and the caller
 * gets a fake "nothing happened". Either recover meaningfully, or let it
 * propagate; absorb only at one boundary, and LOG when you do. Points at exceptions.
 */
final class SwallowCatchDetector implements Detector
{
    public function sin(): Sin
    {
        return new SwallowCatch();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isSwallowedCatch())
            ->get();
    }
}
