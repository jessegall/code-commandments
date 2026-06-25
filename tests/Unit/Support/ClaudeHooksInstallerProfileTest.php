<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\ClaudeHooksInstaller;
use JesseGall\CodeCommandments\Support\Profiles\ProfileRegistry;
use PHPUnit\Framework\TestCase;

class ClaudeHooksInstallerProfileTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-hooks-profile-' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function build(string $profile, bool $planLoop = false): array
    {
        $opts = ProfileRegistry::get($profile)->options();

        return ClaudeHooksInstaller::buildForProfile(ClaudeHooksInstaller::STANDALONE[0], ClaudeHooksInstaller::STANDALONE[1], $opts, $planLoop);
    }

    public function test_disabled_owns_no_hooks(): void
    {
        $this->assertSame([], $this->build('disabled'));
    }

    public function test_grind_has_briefing_and_keep_going_but_no_per_phase_nudges(): void
    {
        $cfg = $this->build('grind');
        $events = array_keys($cfg);

        $this->assertContains('SessionStart', $events);
        $this->assertContains('UserPromptSubmit', $events);
        // grind keeps going (Stop = keep-going hook) but has no per-phase PostToolUse nudge.
        $this->assertContains('Stop', $events);
        $this->assertStringContainsString('stop-hook.sh', json_encode($cfg['Stop']));
        $this->assertNotContains('PostToolUse', $events);
    }

    public function test_disabled_has_no_keep_going_stop(): void
    {
        $this->assertSame([], $this->build('disabled'));
    }

    public function test_active_profiles_inject_skills_on_entering_plan_mode(): void
    {
        foreach (['grind', 'phased', 'sins-only'] as $profile) {
            $pre = json_encode($this->build($profile)['PreToolUse'] ?? []);
            $this->assertStringContainsString('EnterPlanMode|ExitPlanMode', $pre, "{$profile} must hook plan mode");
            $this->assertStringContainsString('skills', $pre);
        }

        $this->assertArrayNotHasKey('PreToolUse', $this->build('disabled'));
    }

    public function test_plan_mode_skills_hook_survives_alongside_the_plan_loop(): void
    {
        $pre = json_encode($this->build('phased', planLoop: true)['PreToolUse'] ?? []);

        $this->assertStringContainsString('EnterPlanMode|ExitPlanMode', $pre);
        $this->assertStringContainsString('guard-plan-marker.sh', $pre); // plan-loop entry still there
    }

    public function test_phased_stop_is_keep_going(): void
    {
        $cfg = $this->build('phased');

        $this->assertStringContainsString('stop-hook.sh', json_encode($cfg['Stop'] ?? []));
        // The old informational `judge --git` Stop is gone (keep-going judges + blocks).
        $this->assertStringNotContainsString('judge --git', json_encode($cfg['Stop'] ?? []));
    }

    public function test_phased_has_per_phase_nudges(): void
    {
        $events = array_keys($this->build('phased'));

        $this->assertContains('SessionStart', $events);
        $this->assertContains('UserPromptSubmit', $events);
        $this->assertContains('Stop', $events);
        $this->assertContains('PostToolUse', $events);
    }

    // --- grind must NOT judge between phases, even with the plan loop on ---

    public function test_grind_with_plan_loop_drives_but_never_judges_each_phase(): void
    {
        $cfg = $this->build('grind', planLoop: true);

        // The plan loop still drives to completion…
        $this->assertStringContainsString('stop-hook.sh', json_encode($cfg['Stop'] ?? []));
        $this->assertStringContainsString('plan-approved.sh', json_encode($cfg['PostToolUse'] ?? []));

        // …but the per-commit JUDGE nudge (phase-committed) is gone, and there is
        // no `judge --git` Stop hook either. grind reckons once at the end.
        $this->assertStringNotContainsString('phase-committed.sh', json_encode($cfg['PostToolUse'] ?? []));
        $this->assertStringNotContainsString('judge --git', json_encode($cfg['Stop'] ?? []));
    }

    public function test_phased_with_plan_loop_judges_each_phase(): void
    {
        $cfg = $this->build('phased', planLoop: true);

        $this->assertStringContainsString('phase-committed.sh', json_encode($cfg['PostToolUse'] ?? []));
    }

    private function settings(): array
    {
        return json_decode((string) @file_get_contents($this->dir . '/.claude/settings.local.json'), true) ?: [];
    }

    public function test_writeForProfile_creates_for_an_active_profile(): void
    {
        $status = ClaudeHooksInstaller::writeForProfile($this->dir, ProfileRegistry::get('phased')->options(), false);

        $this->assertSame(ClaudeHooksInstaller::STATUS_INSTALLED, $status);
        $this->assertArrayHasKey('SessionStart', $this->settings()['hooks'] ?? []);
    }

    public function test_writeForProfile_does_not_create_for_disabled(): void
    {
        $status = ClaudeHooksInstaller::writeForProfile($this->dir, ProfileRegistry::get('disabled')->options(), false);

        $this->assertSame(ClaudeHooksInstaller::STATUS_NO_SETTINGS, $status);
        $this->assertFileDoesNotExist($this->dir . '/.claude/settings.local.json');
    }

    public function test_writeForProfile_strips_a_retired_keep_going_stop_entry(): void
    {
        // Migration (#197): a consumer updated from an older package version has a
        // stale Stop entry pointing at the retired profile-keep-going.sh — it must
        // be stripped and replaced by the single stop-hook.sh, not left dangling.
        mkdir($this->dir . '/.claude', 0755, true);
        file_put_contents($this->dir . '/.claude/settings.local.json', json_encode([
            'hooks' => [
                'Stop' => [
                    ['hooks' => [['type' => 'command', 'command' => 'sh .claude/hooks/profile-keep-going.sh']]],
                    ['hooks' => [['type' => 'command', 'command' => 'sh .claude/hooks/keep-going.sh']]],
                ],
            ],
        ]));

        ClaudeHooksInstaller::writeForProfile($this->dir, ProfileRegistry::get('grind')->options(), false);

        $stop = json_encode($this->settings()['hooks']['Stop'] ?? []);
        $this->assertStringContainsString('stop-hook.sh', $stop);
        $this->assertStringNotContainsString('profile-keep-going.sh', $stop);
        $this->assertStringNotContainsString('keep-going.sh', $stop);
    }

    public function test_writeForProfile_migrates_our_entries_out_of_committed_settings_keeping_consumer_entries(): void
    {
        // A legacy consumer had OUR hooks committed in settings.json alongside their
        // own permission + SessionStart hook. The first profile write must MIGRATE
        // our entries out of the committed file (so they stop being checked in /
        // double-firing) while leaving every consumer key untouched — and (re)write
        // our wiring to the local settings.local.json.
        mkdir($this->dir . '/.claude', 0755, true);
        file_put_contents($this->dir . '/.claude/settings.json', json_encode([
            'permissions' => ['allow' => ['Bash(ls)']],
            'hooks' => [
                'SessionStart' => [
                    ['hooks' => [['type' => 'command', 'command' => 'echo mine']]],
                    ['hooks' => [['type' => 'command', 'command' => 'vendor/bin/commandments scripture 2>/dev/null || true']]],
                ],
            ],
        ]));

        ClaudeHooksInstaller::writeForProfile($this->dir, ProfileRegistry::get('phased')->options(), false);

        // Committed settings.json: consumer keys survive, OUR owned hook is gone.
        $committed = json_decode((string) @file_get_contents($this->dir . '/.claude/settings.json'), true) ?: [];
        $this->assertArrayHasKey('permissions', $committed);
        $committedJson = json_encode($committed);
        $this->assertStringContainsString('echo mine', $committedJson);
        $this->assertStringNotContainsString('commandments scripture', $committedJson);

        // Local settings.local.json: our wiring lives here now.
        $this->assertArrayHasKey('SessionStart', $this->settings()['hooks'] ?? []);
        $this->assertStringContainsString('commandments scripture', json_encode($this->settings()['hooks']));
    }
}
