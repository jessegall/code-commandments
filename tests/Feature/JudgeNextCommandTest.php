<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Prophets\Backend\NoRawRequestProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class JudgeNextCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('commandments.scrolls.backend.path', __DIR__ . '/../Fixtures/Backend/Sinful');
        $app['config']->set('commandments.scrolls.backend.prophets', [
            NoRawRequestProphet::class,
        ]);
    }

    public function test_next_shows_one_finding_and_fails(): void
    {
        $this->artisan('commandments:judge', ['--next' => true])
            ->expectsOutputToContain('NEXT')
            ->assertFailed();
    }

    public function test_next_points_at_fix_or_absolve(): void
    {
        $this->artisan('commandments:judge', ['--next' => true])
            ->expectsOutputToContain('there is no skip')
            ->assertFailed();
    }
}
