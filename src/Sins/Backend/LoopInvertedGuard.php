<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\GuardClausesAndFlow;

final class LoopInvertedGuard extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'loop-inverted-guard',
            skill: GuardClausesAndFlow::class,
            description: "Loop body (multi-statement) wrapped in an `if` instead of `continue` guard",
            rule: "Use a `continue` guard so the loop body stays flat; don't wrap the whole body in an `if`."
        );
    }
}
