<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\SinRepenter;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\RepentanceResult;
use JesseGall\CodeCommandments\Tests\TestCase;

/**
 * Regression for #15: a SinRepenter prophet that emits a finding which is NOT
 * mechanically fixable (per-finding autoFixable=false) must not be labelled
 * [AUTO-FIXABLE], and the "run repent" nudge must not appear — otherwise the
 * agent runs `repent`, it no-ops, and the sin is stuck.
 */
class AutoFixableLabelTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('commandments.scrolls.backend.path', __DIR__ . '/../Fixtures/Backend/AutoFixableLabel');
        $app['config']->set('commandments.scrolls.backend.prophets', [
            NonFixableRepenterStub::class,
        ]);
    }

    public function test_non_fixable_repenter_findings_are_not_labelled_auto_fixable(): void
    {
        $this->artisan('commandments:judge')
            ->doesntExpectOutputToContain('[AUTO-FIXABLE]')
            ->doesntExpectOutputToContain('repent')
            ->assertFailed();
    }
}

/**
 * Always-supported repenter whose single finding is explicitly NOT auto-fixable.
 */
class NonFixableRepenterStub extends PhpCommandment implements SinRepenter
{
    public function description(): string
    {
        return 'Stub: emits a non-fixable sin.';
    }

    public function detailedDescription(): string
    {
        return 'Stub used by AutoFixableLabelTest.';
    }

    public function judge(string $filePath, string $content): Judgment
    {
        // symbol + autoFixable=false (the last two args).
        return $this->fallen([
            $this->sinAt(1, 'A sin that must be fixed by hand.', 'class Sample', 'Fix it by hand.', 'Sample', false),
        ]);
    }

    public function canRepent(string $filePath): bool
    {
        return true;
    }

    public function repent(string $filePath, string $content): RepentanceResult
    {
        return RepentanceResult::unchanged();
    }
}
