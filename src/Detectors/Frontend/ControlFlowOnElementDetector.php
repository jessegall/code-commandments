<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\ControlFlowOnElement;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Frontend\WrapControlFlowScribe;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;
use JesseGall\CodeCommandments\Vue\Directive;

/**
 * A control-flow directive — `v-if` / `v-else-if` / `v-else` / `v-for` — sitting on a
 * real element or component instead of a `<template>`. Mixing structure (which DOM
 * renders) with content (the element itself) makes a template harder to read and
 * couples the condition/loop to one tag; the structure belongs on a `<template>`
 * wrapper, the element stays pure content. Points at vue-control-flow.
 *
 * `v-show` is NOT flagged — it toggles `display` on a real element and cannot live on a
 * `<template>` (which renders no node). Only the directives that change the STRUCTURE
 * ({@see Directive::structural}) are the sin.
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
            ->get();
    }
}
