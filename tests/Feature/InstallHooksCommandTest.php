<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Tests\TestCase;

class InstallHooksCommandTest extends TestCase
{
    /**
     * `install-hooks` now installs the skills under .claude/skills/commandments/,
     * so .claude has a nested tree — a shallow glob/unlink/rmdir can't remove it.
     */
    private function removeClaudeDir(): void
    {
        $claudeDir = base_path('.claude');

        if (is_dir($claudeDir)) {
            shell_exec('rm -rf ' . escapeshellarg($claudeDir));
        }
    }

    public function test_install_hooks_command_runs(): void
    {
        $this->artisan('commandments:install-hooks')
            ->assertSuccessful();
    }

    public function test_install_hooks_creates_claude_directory(): void
    {
        $claudeDir = base_path('.claude');

        // Remove if exists
        $this->removeClaudeDir();

        $this->artisan('commandments:install-hooks')
            ->assertSuccessful();

        $this->assertDirectoryExists($claudeDir);

        // Cleanup
        $this->removeClaudeDir();
    }

    public function test_install_hooks_creates_settings_file(): void
    {
        $settingsFile = base_path('.claude/settings.json');

        // Remove if exists
        if (file_exists($settingsFile)) {
            unlink($settingsFile);
        }

        $this->artisan('commandments:install-hooks')
            ->assertSuccessful();

        $this->assertFileExists($settingsFile);

        // Verify JSON structure
        $content = json_decode(file_get_contents($settingsFile), true);
        $this->assertArrayHasKey('hooks', $content);
        $this->assertArrayHasKey('SessionStart', $content['hooks']);
        $this->assertArrayHasKey('Stop', $content['hooks']);

        // The handoff-detect SessionStart hook offers a resume when HANDOFF.md exists.
        $commands = [];
        foreach ($content['hooks']['SessionStart'] as $group) {
            foreach ($group['hooks'] ?? [] as $h) {
                $commands[] = $h['command'] ?? '';
            }
        }
        $this->assertContains('sh .claude/hooks/handoff-detect.sh 2>/dev/null || true', $commands);

        // Cleanup
        $this->removeClaudeDir();
    }

    public function test_install_hooks_does_not_write_a_claude_md_section(): void
    {
        $claudeMdPath = base_path('CLAUDE.md');

        // Seed a CLAUDE.md carrying a legacy section to prove install-hooks strips it
        // (briefing is hook-delivered now, never committed CLAUDE.md).
        file_put_contents($claudeMdPath, "# App\n\n" . \JesseGall\CodeCommandments\Support\ClaudeMdInstaller::BEGIN . "\nold\n" . \JesseGall\CodeCommandments\Support\ClaudeMdInstaller::END . "\n");

        $this->removeClaudeDir();

        $this->artisan('commandments:install-hooks')
            ->assertSuccessful();

        $this->assertStringNotContainsString('## Code Commandments', file_get_contents($claudeMdPath));
        $this->assertStringNotContainsString(\JesseGall\CodeCommandments\Support\ClaudeMdInstaller::BEGIN, file_get_contents($claudeMdPath));

        // The briefing instead lives in the session-start hook wiring.
        $settings = json_decode((string) file_get_contents(base_path('.claude/settings.json')), true);
        $this->assertArrayHasKey('SessionStart', $settings['hooks'] ?? []);

        // Cleanup
        unlink($claudeMdPath);
        @unlink(base_path('.commandments/profile'));
        $this->removeClaudeDir();
    }
}
