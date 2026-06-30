<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\EnumValueMatch;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A `match`/`switch` over a backed enum's `->value` at a call site — the enum
 * unwrapped to a scalar so it can be dispatched on out here. That mapping is the
 * enum's own job: move it onto the case as a method with an exhaustive `match`,
 * and let the call site just ask. Points at enums-with-behaviour.
 *
 * A `match ($this)` inside the enum is exactly that method, so a match sitting in
 * an enum declaration is left alone.
 */
final class EnumValueMatchDetector implements Detector
{
    public function sin(): Sin
    {
        return new EnumValueMatch();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isMatchOnEnumValue())
            ->reject(static fn (AstNode $node): bool => $node->isInEnum())
            ->get();
    }
}
