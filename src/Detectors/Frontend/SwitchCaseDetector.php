<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Cli\Rewriting\SwitchCaseScribe;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;
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
    private const int CASES = 2;

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
            ->where(static fn (Element $element): bool => $element->hasAttribute('v-if'))
            ->where(static fn (Element $element): bool => self::dispatchesOnOneValue($element))
            ->get();
    }

    /**
     * The chain head and its `v-else-if` siblings every test the same subject for
     * equality, across at least {@see CASES} branches — a value being dispatched on.
     */
    private static function dispatchesOnOneValue(Element $head): bool
    {
        $subject = self::equalitySubject($head->attribute('v-if'));

        if ($subject === null) {
            return false;
        }

        $cases = 1;

        foreach ($head->followingElements() as $sibling) {
            if ($sibling->hasAttribute('v-else-if')) {
                if (self::equalitySubject($sibling->attribute('v-else-if')) !== $subject) {
                    return false;
                }

                $cases++;

                continue;
            }

            // A v-else closes the chain; anything else ends it.
            break;
        }

        return $cases >= self::CASES;
    }

    /**
     * The left operand of an `===` / `==` test, or null when the expression isn't a
     * straight equality (so a `>`/`includes()`/method guard is never mistaken for a case).
     */
    private static function equalitySubject(?string $expression): ?string
    {
        if ($expression !== null && preg_match('/^\s*([A-Za-z_$][\w$.]*)\s*===?\s*/', $expression, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
