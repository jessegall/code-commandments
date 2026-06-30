<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Cli\Rewriting\Frontend\SwitchCaseScribe;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\Element;

/**
 * A `v-if` / `v-else-if` chain whose every branch tests the SAME value against a
 * different case — a switch wearing conditionals. Each `v-else-if` re-states the
 * subject and re-reads as a separate decision, when there is really one: dispatch
 * on a value. Hoist it to a `<SwitchCase :value>` with a slot per case (the
 * published component). Points at vue-control-flow.
 *
 * Repentable — {@see SwitchCaseScribe} rewrites the chain into `<SwitchCase>`.
 */
final class SwitchCaseDetector implements Detector, Repentable
{
    public function skill(): string
    {
        return 'vue-control-flow';
    }

    public function scribe(): string
    {
        return SwitchCaseScribe::class;
    }

    public function find(Codebase $components): array
    {
        return $components
            ->whereElement()
            ->withDirective(Directive::If)
            ->where(static fn (Element $element): bool => SwitchCaseChain::at($element) !== null)
            ->get();
    }
}
