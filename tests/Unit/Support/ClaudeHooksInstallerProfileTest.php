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

    private function build(string $profile): array
    {
        $opts = ProfileRegistry::get($profile)->options();

        return ClaudeHooksInstaller::buildForProfile(ClaudeHooksInstaller::STANDALONE[0], ClaudeHooksInstaller::STANDALONE[1], $opts, false);
    }

    public function test_disabled_owns_no_hooks(): void
    {
        $this->assertSame([], $this->build('disabled'));
    }

    public function test_grind_has_briefing_but_no_per_phase_nudges(): void
    {
        $events = array_keys($this->build('grind'));

        $this->assertContains('SessionStart', $events);
        $this->assertContains('UserPromptSubmit', $events);
        $this->assertNotContains('Stop', $events);
        $this->assertNotContains('PostToolUse', $events);
    }

    public function test_phased_has_per_phase_nudges(): void
    {
        $events = array_keys($this->build('phased'));

        $this->assertContains('SessionStart', $events);
        $this->assertContains('UserPromptSubmit', $events);
        $this->assertContains('Stop', $events);
        $this->assertContains('PostToolUse', $events);
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
