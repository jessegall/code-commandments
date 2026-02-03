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

    public function test_scripture_command_shows_code_commandments(): void
    {
        $this->artisan('commandments:scripture')
            ->expectsOutputToContain('CODE COMMANDMENTS');
    }

    public function test_scripture_command_lists_prophets(): void
    {
        // Prophet names are shown without the "Prophet" suffix
        $this->artisan('commandments:scripture')
            ->expectsOutputToContain('NoRawRequest')
            ->expectsOutputToContain('CompositionApi');
    }

    public function test_scripture_command_with_scroll_filter(): void
    {
        $this->artisan('commandments:scripture', ['--scroll' => 'backend'])
            ->expectsOutputToContain('NoRawRequest')
            ->doesntExpectOutputToContain('CompositionApi');
    }

    public function test_scripture_command_detailed_mode(): void
    {
        $this->artisan('commandments:scripture', ['--detailed' => true])
            ->expectsOutputToContain('Bad:');
    }

    public function test_scripture_command_shows_check_violations(): void
    {
        $this->artisan('commandments:scripture')
            ->expectsOutputToContain('commandments:judge');
    }
}
