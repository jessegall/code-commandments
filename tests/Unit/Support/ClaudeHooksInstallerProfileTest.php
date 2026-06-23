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
        $this->assertStringContainsString('profile-keep-going.sh', json_encode($cfg['Stop']));
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

        $this->assertStringContainsString('profile-keep-going.sh', json_encode($cfg['Stop'] ?? []));
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
        $this->assertStringContainsString('keep-going.sh', json_encode($cfg['Stop'] ?? []));
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
        return json_decode((string) @file_get_contents($this->dir . '/.claude/settings.json'), true) ?: [];
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
        $this->assertFileDoesNotExist($this->dir . '/.claude/settings.json');
    }

    public function test_writeForProfile_strips_owned_but_keeps_consumer_entries(): void
    {
        mkdir($this->dir . '/.claude', 0755, true);
        file_put_contents($this->dir . '/.claude/settings.json', json_encode([
            'permissions' => ['allow' => ['Bash(ls)']],
            'hooks' => [
                'SessionStart' => [
                    ['hooks' => [['type' => 'command', 'command' => 'echo mine']]],
                ],
            ],
        ]));

        // Switch to phased (adds ours), then disabled (strips ours).
        ClaudeHooksInstaller::writeForProfile($this->dir, ProfileRegistry::get('phased')->options(), false);
        ClaudeHooksInstaller::writeForProfile($this->dir, ProfileRegistry::get('disabled')->options(), false);

        $settings = $this->settings();
        // Consumer permission + their own SessionStart hook survive.
        $this->assertArrayHasKey('permissions', $settings);
        $commands = [];
        foreach ($settings['hooks']['SessionStart'] ?? [] as $entry) {
            foreach ($entry['hooks'] as $h) {
                $commands[] = $h['command'];
            }
        }
        $this->assertContains('echo mine', $commands);
        $this->assertNotContains('vendor/bin/commandments scripture 2>/dev/null || true', $commands);
    }
}
