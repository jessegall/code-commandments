<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\LoopInvertedGuard;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\LoopInvertedGuardScribe;

/**
 * A loop whose entire body is wrapped in one `if` — the iteration's real work
 * pushed a level deep behind a condition. Invert it into a `continue` guard
 * (`if (! cond) continue;`) so the body stays flat and the happy path is the
 * loop's top level. Points at guard-clauses-and-flow.
 */
final class LoopInvertedGuardDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new LoopInvertedGuard();
    }

    public function scribe(): string
    {
        return LoopInvertedGuardScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isSoleLoopBodyGuard())
            ->get();
    }
}
