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

    public function test_next_redirects_to_the_pilgrimage_and_fails_while_findings_remain(): void
    {
        // --next is retired — the guided walk is the pilgrimage. It redirects there
        // but still exits non-zero while findings remain (so gate probes keep working).
        $this->artisan('commandments:judge', ['--next' => true])
            ->expectsOutputToContain('PILGRIMAGE')
            ->assertFailed();
    }

    public function test_next_points_at_the_pilgrimage_commands(): void
    {
        $this->artisan('commandments:judge', ['--next' => true])
            ->expectsOutputToContain('pilgrimage')
            ->assertFailed();
    }
}
