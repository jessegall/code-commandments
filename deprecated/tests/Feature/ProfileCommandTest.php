<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Tests\TestCase;

class ProfileCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(base_path('.commandments/profile'));
        @unlink(base_path('.commandments/profile-last-briefed'));
        parent::tearDown();
    }

    public function test_list_shows_every_profile(): void
    {
        $this->artisan('commandments:profile', ['name' => 'list'])
            ->expectsOutputToContain('grind')
            ->expectsOutputToContain('phased')
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();
    }

    public function test_show_reports_the_active_profile(): void
    {
        // (The clean-env disabled default is covered by ProfileServiceTest; the
        // testbench base path carries ambient markers, so just assert it reports.)
        $this->artisan('commandments:profile')
            ->expectsOutputToContain('Active profile:')
            ->assertSuccessful();
    }

    public function test_switch_records_the_selection(): void
    {
        $this->artisan('commandments:profile', ['name' => 'grind'])
            ->expectsOutputToContain('grind')
            ->assertSuccessful();

        $this->assertSame('grind', trim((string) file_get_contents(base_path('.commandments/profile'))));
    }

    public function test_unknown_profile_fails(): void
    {
        $this->artisan('commandments:profile', ['name' => 'nonsense'])
            ->expectsOutputToContain('Unknown profile')
            ->assertFailed();
    }

    public function test_brief_emits_the_active_contract(): void
    {
        $this->artisan('commandments:profile', ['name' => 'grind'])->assertSuccessful();

        $this->artisan('commandments:profile', ['--brief' => true])
            ->expectsOutputToContain('grind')
            ->assertSuccessful();
    }

    public function test_drift_check_runs_cleanly(): void
    {
        $this->artisan('commandments:profile', ['name' => 'phased'])->assertSuccessful();

        // First drift-check after a switch re-briefs; a second is silent. Both exit 0.
        $this->artisan('commandments:profile', ['--drift-check' => true])->assertSuccessful();
        $this->artisan('commandments:profile', ['--drift-check' => true])->assertSuccessful();
    }
}
