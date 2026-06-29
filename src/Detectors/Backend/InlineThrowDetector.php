<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A `?? throw` buried inside a larger expression — fed into a call or
 * dereferenced on the same line instead of guarded at the top. Points at
 * guard-clauses-and-flow.
 *
 * A bare `return $x ?? throw ...;` (the throw is the whole expression) is fine;
 * the smell is `f($x ?? throw ...)` or `($x ?? throw ...)->y()`.
 */
final class InlineThrowDetector implements Detector
{
    public function skill(): string
    {
        return 'guard-clauses-and-flow';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->coalesceRight()->isThrow())
            ->where(static fn (AstNode $node): bool => $node->isCallArgument() || $node->isCallReceiver())
            ->get();
    }
}
