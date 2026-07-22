<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\MatchDefaultReturnsNullDetector;
use PHPUnit\Framework\TestCase;

final class MatchDefaultReturnsNullDetectorTest extends TestCase
{
    public function test_flags_a_match_default_returning_absence_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S {
            public function a($x) { return match ($x) { 1 => 'one', default => null }; }
            public function b($x) { return match ($x) { 1 => 'one', default => [] }; }
            public function c($x) { return match ($x) { 1 => 'one', default => 'other' }; }
            public function d($x) { return match ($x) { 1 => 'one', default => throw new \RuntimeException }; }
        }
        PHP;

        $hits = (new MatchDefaultReturnsNullDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        // a (null) + b ([]) — not c (real value), not d (throws).
        $this->assertSame(['S::a', 'S::b'], $scopes);
    }

    public function test_does_not_flag_when_handled_arms_already_admit_null_over_an_open_subject(): void
    {
        // The recognised brands call `?string` heuristics — "no suggestion" is part of the
        // match's own answer vocabulary, so `default => null` gives the unrecognised rest
        // the same declared answer, not a swallowed unhandled case (#393).
        $code = <<<'PHP'
        <?php
        namespace App;

        class Guesser
        {
            public function guess(string $brand, string $haystack): string|null
            {
                return match ($brand) {
                    'PostNL' => $this->guessPostNl($haystack),
                    'DHL' => $this->guessDhl($haystack),
                    default => null,
                };
            }

            private function guessPostNl(string $haystack): string|null { return null; }

            private function guessDhl(string $haystack): string|null { return 'dhl-parcel'; }
        }
        PHP;

        $this->assertSame([], (new MatchDefaultReturnsNullDetector)->find(Codebase::fromString($code)));
    }

    public function test_still_flags_an_enum_subject_even_when_an_arm_admits_null(): void
    {
        // An enum is a CLOSED set — a swallowing default hides a hole regardless of what
        // the handled arms return.
        $code = <<<'PHP'
        <?php
        namespace App;

        enum Phase { case Setup; case Ready; }

        class Wizard
        {
            public function banner(Phase $phase): string|null
            {
                return match ($phase) {
                    Phase::Setup => $this->setupBanner(),
                    default => null,
                };
            }

            private function setupBanner(): string|null { return null; }
        }
        PHP;

        $hits = (new MatchDefaultReturnsNullDetector)->find(Codebase::fromString($code));

        $this->assertSame(['App\\Wizard::banner'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
