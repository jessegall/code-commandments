<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * `throw new X("…message…")` — the failure described with a prose string at the
 * throw site instead of a named factory carrying domain VALUES
 * (`throw OrderNotFound::forId($id)`). The message belongs ON the exception,
 * built from the data it's handed, so every throw of that failure reads the same.
 * Points at exceptions.
 */
final class MessageAtThrowDetector implements Detector
{
    public function skill(): string
    {
        return 'exceptions';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNew()
            ->where(static fn (AstNode $node): bool => $node->isThrownWithMessage())
            ->get();
    }
}
