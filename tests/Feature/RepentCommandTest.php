<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Tests\TestCase;

class RepentCommandTest extends TestCase
{
    public function test_repent_command_runs(): void
    {
        $this->artisan('commandments:repent')
            ->assertSuccessful();
    }

    public function test_repent_command_dry_run(): void
    {
        $this->artisan('commandments:repent', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN MODE')
            ->assertSuccessful();
    }

    public function test_repent_command_shows_seeking_absolution(): void
    {
        $this->artisan('commandments:repent')
            ->expectsOutputToContain('SEEKING ABSOLUTION');
    }

    public function test_repent_command_with_scroll_filter(): void
    {
        $this->artisan('commandments:repent', ['--scroll' => 'backend'])
            ->assertSuccessful();
    }

    public function test_repent_command_with_unknown_scroll(): void
    {
        $this->artisan('commandments:repent', ['--scroll' => 'unknown'])
            ->expectsOutputToContain('Unknown scroll');
    }
}
