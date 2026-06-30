<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\WrappingWithoutCause;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Throwing a new exception inside a `catch` without passing the caught one on as
 * its cause (`previous`) — the original failure and its stack trace are dropped,
 * so the wrapped error lies about where it came from. Chain the cause. Points at
 * exceptions.
 */
final class WrappingWithoutCauseDetector implements Detector
{
    public function sin(): Sin
    {
        return new WrappingWithoutCause();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNew()
            ->where(static fn (AstNode $node): bool => $node->isRethrowWithoutCause())
            ->get();
    }
}
