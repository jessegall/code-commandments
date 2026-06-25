<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Prophets\Backend\NoRawRequestProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class JudgeCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Only scan the Righteous fixtures to ensure no sins are found
        $app['config']->set('commandments.scrolls.backend.path', __DIR__ . '/../Fixtures/Backend/Righteous');
        $app['config']->set('commandments.scrolls.backend.prophets', [
            NoRawRequestProphet::class,
        ]);
    }

    public function test_judge_command_runs(): void
    {
        // Command runs and finds no sins in righteous fixtures
        $this->artisan('commandments:judge')
            ->assertSuccessful();
    }

    public function test_judge_command_with_scroll_filter(): void
    {
        // Command runs with scroll filter
        $this->artisan('commandments:judge', ['--scroll' => 'backend'])
            ->assertSuccessful();
    }

    public function test_judge_command_shows_righteous_when_no_sins(): void
    {
        $this->artisan('commandments:judge')
            ->expectsOutputToContain('Righteous');
    }

    public function test_judge_command_with_unknown_scroll(): void
    {
        // Unknown scrolls are silently skipped in the new plain output
        $this->artisan('commandments:judge', ['--scroll' => 'unknown'])
            ->assertSuccessful();
    }
}
