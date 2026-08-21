<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Cli\Judge\DetectorRunner;
use JesseGall\CodeCommandments\Cli\Judge\Views;
use JesseGall\CodeCommandments\Finding;
use JesseGall\CodeCommandments\Cli\ProgressBar;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\FacadeCallDetector;
use JesseGall\CodeCommandments\Detectors\Backend\RepeatedGuardDetector;
use PHPUnit\Framework\TestCase;

/**
 * The runner recovers each recurrence finding's BUCKET — the other occurrences its verdict rests on — by
 * re-reading the detector's own fingerprint. Two guards that merely rhyme must not be handed each other
 * as twins; that misreading is what makes a correct recurrence finding look like a false positive.
 */
final class RecurrenceFindingTwinsTest extends TestCase
{
    /** @return list<Finding> */
    private function judge(object $detector, string $code): array
    {
        return new DetectorRunner(1)->run([$detector], Views::whole(Codebase::fromString($code)), new ProgressBar)->findings;
    }

    public function test_a_recurrence_finding_carries_its_bucket(): void
    {
        $code = <<<'PHP'
        <?php
        class Gate
        {
            public function allow(): bool
            {
                return $this->user->active && $this->user->verified;
            }

            public function audit(): bool
            {
                return $this->user->verified && $this->user->active;
            }
        }
        PHP;

        $findings = $this->judge(new RepeatedGuardDetector, $code);

        // Order-blind: the same guard spelled both ways is ONE shape, so each names the other.
        $this->assertSame([['6'], ['11']], array_map(
            static fn (Finding $f): array => array_map(static fn (string $t): string => explode(':', $t)[1], $f->twins),
            array_reverse($findings),
        ));
    }

    public function test_guards_that_merely_rhyme_are_not_twins(): void
    {
        // The shape reported in issue #383: same skeleton (`<nullable> !== null && <comparison>`), but
        // different fields AND different comparisons. Two unrelated decisions — never one bucket.
        $code = <<<'PHP'
        <?php
        class PrintAgent
        {
            public function isTokenExpired(): bool
            {
                return $this->token_expires_at !== null && $this->token_expires_at->isPast();
            }

            public function isOnline(int $fresh): bool
            {
                return $this->last_heartbeat_at !== null && $this->last_heartbeat_at->gt($fresh);
            }
        }
        PHP;

        $this->assertSame([], $this->judge(new RepeatedGuardDetector, $code));
    }

    public function test_an_ordinary_detector_reports_no_twins(): void
    {
        $code = <<<'PHP'
        <?php
        use Illuminate\Support\Facades\Cache;

        class Service
        {
            public function cached(): string
            {
                return Cache::get('k');
            }
        }
        PHP;

        $findings = $this->judge(new FacadeCallDetector, $code);

        $this->assertCount(1, $findings);
        $this->assertSame([], $findings[0]->twins, 'a site judged on its own has nothing to be bucketed with');
    }
}
