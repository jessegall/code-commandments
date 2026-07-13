<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\ControlFlowOnElement;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Frontend\WrapControlFlowScribe;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * Detects structural directives (`v-if`/`v-else-if`/`v-else`/`v-for`) on real elements
 * instead of `<template>` wrappers — mixes structure (what renders) with content. Exempts
 * `v-show` (affects real element only) and the direct child of a
 * `<Transition>`/`<TransitionGroup>`, where Vue REQUIRES the directive on the real keyed
 * element. Points at vue-control-flow.
 */
final class ControlFlowOnElementDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new ControlFlowOnElement();
    }

    public function scribe(): string
    {
        return WrapControlFlowScribe::class;
    }

    public function find(Codebase $components): array
    {
        return $components
            ->whereElement()
            ->rejectTag('template')
            ->withAnyDirective(...Directive::structural())
            ->reject(static fn (ElementMatch $element): bool => $element->isTransitionChild())
            ->get();
    }
}
