<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A loop whose entire body is wrapped in one `if` — the iteration's real work
 * pushed a level deep behind a condition. Invert it into a `continue` guard
 * (`if (! cond) continue;`) so the body stays flat and the happy path is the
 * loop's top level. Points at guard-clauses-and-flow.
 */
final class LoopInvertedGuardDetector implements Detector
{
    public function skill(): string
    {
        return 'backend/guard-clauses-and-flow';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isSoleLoopBodyGuard())
            ->get();
    }
}
