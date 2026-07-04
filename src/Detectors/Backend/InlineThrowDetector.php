<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\InlineThrow;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects `?? throw` buried in a call arg or dereference, not bare return statements.
 */
final class InlineThrowDetector implements Detector
{
    public function sin(): Sin
    {
        return new InlineThrow();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->coalesceRight()->isThrow())
            ->where(static fn (AstNode $node): bool => $node->isCallArgument() || $node->isCallReceiver())
            ->get();
    }
}
