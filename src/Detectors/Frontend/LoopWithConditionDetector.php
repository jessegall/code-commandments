<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\LoopWithCondition;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Directive;

/**
 * Detects `v-for` and `v-if`/`v-else-if` on the same element (Vue prioritizes if over for, causing
 * correctness/performance issues). Filter in a computed or hoist to template+child. Points at vue-control-flow.
 */
final class LoopWithConditionDetector implements Detector
{
    public function sin(): Sin
    {
        return new LoopWithCondition();
    }

    public function find(Codebase $components): array
    {
        return $components
            ->whereElement()
            ->withDirective(Directive::For)
            ->withAnyDirective(Directive::If, Directive::ElseIf)
            ->get();
    }
}
