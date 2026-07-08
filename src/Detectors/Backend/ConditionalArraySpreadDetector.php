<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\ConditionalArraySpread;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a conditional array-element spread — `...($x ? ['k' => $x] : [])` inside an array literal, or
 * `array_merge($base, $cond ? [...] : [])` — the ternary-into-empty-array noise. The clean shape is a
 * null-dropping variadic factory on the target type (`::of(mixed ...$values)`), so a null-valued named
 * argument simply vanishes with no ternary and no conditional spread.
 */
final class ConditionalArraySpreadDetector implements Detector
{
    public function sin(): Sin
    {
        return new ConditionalArraySpread();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isConditionalArraySpread())
            ->get();
    }
}
