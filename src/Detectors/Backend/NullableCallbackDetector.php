<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A nullable callback (`?callable $cb = null`) that the body null-normalises
 * before calling — `if ($cb !== null) { $cb(…); }`, `($cb ?? fn () => …)(…)`.
 * That guard is a Null Object wearing a disguise: default the param to a no-op
 * callable and call it unconditionally. Points at absence.
 */
final class NullableCallbackDetector implements Detector
{
    public function skill(): string
    {
        return 'absence';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (AstNode $node): bool => $node->hasNullNormalisedNullableCallback())
            ->get();
    }
}
