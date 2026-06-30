<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\MaskedInvariant;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\OwnStateMask;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * `$this->scratch?->call() ?? false` — defaulting a reach into the object's own
 * TRANSIENT nullable state. The field is set inside a method, so at this call site
 * it should be present; the `?->… ?? <literal>` manufactures a fake answer for a
 * state that can only be a bug (a control handle quietly classified as data flow).
 * Resolve-or-throw, or remove the per-call scratch state so the field is never
 * null. Points at type-honesty.
 */
final class MaskedInvariantDetector implements Detector
{
    public function sin(): Sin
    {
        return new MaskedInvariant();
    }

    public function find(Codebase $codebase): array
    {
        $mask = OwnStateMask::forCodebase($codebase);

        return $codebase
            ->where(static fn (AstNode $node): bool => $mask->masksOwnState($node))
            ->get();
    }
}
