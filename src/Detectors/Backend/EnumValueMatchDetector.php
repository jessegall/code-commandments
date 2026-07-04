<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\EnumValueMatch;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * Detects `match`/`switch` over a backed enum's `->value` at a call site. That mapping belongs
 * on the enum case as a method with an exhaustive `match`. Ignores `match ($this)` inside the enum.
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
