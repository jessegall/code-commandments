<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\SwitchCase;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Frontend\SwitchCaseScribe;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\SwitchCaseChain;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Directive;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * Detects `v-if`/`v-else-if` chains testing the same value against different cases (switch as conditionals).
 * Hoist to `<SwitchCase :value>` with slots per case. Points at vue-control-flow; repentable.
 */
final class SwitchCaseDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new SwitchCase();
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
            ->where(static fn (ElementMatch $element): bool => SwitchCaseChain::at($element) !== null)
            ->get();
    }
}
