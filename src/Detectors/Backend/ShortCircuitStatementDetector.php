<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\ShortCircuitStatementScribe;
use JesseGall\CodeCommandments\Sins\Backend\ShortCircuitStatement;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A short-circuit operator standing as a whole statement — `$node->isBuilt() && $node->built()->forget();`.
 * Nothing reads the boolean it produces, so the `&&` isn't computing a value at all: it is a
 * CONDITION and a consequence, written as an expression to save an `if`. The branch is real
 * either way; only its shape is hidden, at the exact place a body's flow should be readable. A
 * `throw` on the right is exempt — `$x instanceof Y || throw Refused::for($x);` is an ASSERTION,
 * the precondition and its bail side by side, hiding nothing because its only outcome is leaving;
 * the same line {@see InlineThrowDetector} draws for a `?? throw` that feeds nothing.
 */
final class ShortCircuitStatementDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new ShortCircuitStatement();
    }

    public function scribe(): string
    {
        return ShortCircuitStatementScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isShortCircuit())
            ->where(static fn (AstNode $node): bool => $node->resultIsDiscarded())
            ->reject(static fn (AstNode $node): bool => $node->shortCircuitRight()->isThrow())
            ->get();
    }
}
