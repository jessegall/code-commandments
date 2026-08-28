<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Hooks;

use JesseGall\CodeCommandments\Hooks\StopHookCap;
use PHPUnit\Framework\TestCase;

/**
 * A blocking hook has to give way before the harness does.
 *
 * Claude Code overrides a `Stop` hook that has blocked too many turns in a row, and that override
 * keeps nothing — the gate still holding is dropped and the user's conditions leave the session
 * unsaid. Every backstop we own is therefore measured against the harness's cap rather than against
 * a number of our own choosing.
 */
final class StopHookCapTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv(StopHookCap::VARIABLE);
    }

    public function test_the_budget_stops_one_short_of_the_harness_cap(): void
    {
        putenv(StopHookCap::VARIABLE . '=10');

        $this->assertSame(9, StopHookCap::budget(50));
    }

    public function test_a_hook_asking_for_less_than_the_cap_keeps_its_own_number(): void
    {
        putenv(StopHookCap::VARIABLE . '=100');

        $this->assertSame(50, StopHookCap::budget(50));
    }

    public function test_an_unconfigured_session_falls_back_to_the_harness_default(): void
    {
        putenv(StopHookCap::VARIABLE);

        $this->assertSame(8, StopHookCap::budget(50));
    }

    public function test_a_meaningless_cap_is_not_taken_at_its_word(): void
    {
        foreach (['0', '-3', 'plenty', ''] as $configured) {
            putenv(StopHookCap::VARIABLE . '=' . $configured);

            $this->assertSame(8, StopHookCap::budget(50), "A cap of '{$configured}' should fall back to the default.");
        }
    }
}
