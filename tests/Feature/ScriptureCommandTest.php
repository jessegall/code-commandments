<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Prophets\Backend\NoRawRequestProphet;
use JesseGall\CodeCommandments\Prophets\Frontend\CompositionApiProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class ScriptureCommandTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('commandments.scrolls.backend.prophets', [
            NoRawRequestProphet::class,
        ]);

        $app['config']->set('commandments.scrolls.frontend.prophets', [
            CompositionApiProphet::class,
        ]);
    }

    public function test_scripture_command_runs(): void
    {
        $this->artisan('commandments:scripture')
            ->assertSuccessful();
    }

    public function test_scripture_command_shows_sacred_scripture(): void
    {
        $this->artisan('commandments:scripture')
            ->expectsOutputToContain('SACRED SCRIPTURE');
    }

    public function test_scripture_command_lists_prophets(): void
    {
        $this->artisan('commandments:scripture')
            ->expectsOutputToContain('NoRawRequestProphet')
            ->expectsOutputToContain('CompositionApiProphet');
    }

    public function test_scripture_command_with_scroll_filter(): void
    {
        $this->artisan('commandments:scripture', ['--scroll' => 'backend'])
            ->expectsOutputToContain('NoRawRequestProphet')
            ->doesntExpectOutputToContain('CompositionApiProphet');
    }

    public function test_scripture_command_detailed_mode(): void
    {
        $this->artisan('commandments:scripture', ['--detailed' => true])
            ->expectsOutputToContain('Bad:');
    }

    public function test_scripture_command_shows_total_count(): void
    {
        $this->artisan('commandments:scripture')
            ->expectsOutputToContain('Total commandments:');
    }
}
