<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\PositionalTupleReturn;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects positional tuples — keyless arrays without types or names that callers must
 * destructure by position. Order changes break silently; use a typed bundle instead.
 * Points at value-objects.
 */
final class PositionalTupleReturnDetector implements Detector
{
    public function sin(): Sin
    {
        return new PositionalTupleReturn();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isPositionalTuple())
            ->where(static fn (AstNode $node): bool => $node->isReturnExpression())
            ->get();
    }
}
