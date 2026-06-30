<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\LoopWithCondition;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;
use JesseGall\CodeCommandments\Vue\Directive;

/**
 * A `v-for` and a `v-if`/`v-else-if` on the SAME element. Vue gives `v-if` higher priority
 * than `v-for`, so the condition can't even read the loop variable, and where it can it is
 * re-evaluated on every iteration — both a correctness trap and wasted work. The fix is to
 * filter the list in a computed, or hoist the `v-for` onto a `<template>` wrapper and put the
 * `v-if` on the child. Points at vue-control-flow.
 *
 * The correct form keeps the two directives on DIFFERENT elements (the `<template v-for>`
 * around a `v-if` child), so it never matches — no name list, no heuristic, just the two
 * directives sharing one tag.
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
