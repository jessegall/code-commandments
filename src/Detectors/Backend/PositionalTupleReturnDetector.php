<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Returning a positional TUPLE — `return [$node, $key, $inputs, $outputs]` (also
 * from a closure / arrow fn) — bundles several independent values as a keyless
 * list the caller must destructure by position. Order is unchecked and the parts
 * are unnamed: `[$a, $b] = f()` silently rots when the order changes. Give the
 * bundle a type and return that. Points at value-objects.
 *
 * A single-source projection (`[$row->a, $row->b, $row->c]`) or a list of literals
 * is a collection, not a tuple, and is left alone.
 */
final class PositionalTupleReturnDetector implements Detector
{
    public function skill(): string
    {
        return 'value-objects';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isPositionalTuple())
            ->where(static fn (AstNode $node): bool => $node->isReturnExpression())
            ->get();
    }
}
