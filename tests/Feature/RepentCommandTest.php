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
        // With righteous fixtures, dry-run just reports no sins
        $this->artisan('commandments:repent', ['--dry-run' => true])
            ->expectsOutputToContain('righteous')
            ->assertSuccessful();
    }

    public function test_repent_command_shows_righteous_when_no_sins(): void
    {
        $this->artisan('commandments:repent')
            ->expectsOutputToContain('righteous');
    }

    public function test_repent_command_with_scroll_filter(): void
    {
        $this->artisan('commandments:repent', ['--scroll' => 'backend'])
            ->assertSuccessful();
    }

    public function test_repent_command_with_unknown_scroll(): void
    {
        // Unknown scrolls are silently skipped
        $this->artisan('commandments:repent', ['--scroll' => 'unknown'])
            ->assertSuccessful();
    }
}
