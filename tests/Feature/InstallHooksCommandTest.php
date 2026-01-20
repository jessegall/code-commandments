<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Tests\TestCase;

class InstallHooksCommandTest extends TestCase
{
    public function test_install_hooks_command_runs(): void
    {
        $this->artisan('commandments:install-hooks')
            ->assertSuccessful();
    }

    public function test_install_hooks_creates_claude_directory(): void
    {
        $claudeDir = base_path('.claude');

        // Remove if exists
        if (is_dir($claudeDir)) {
            array_map('unlink', glob("{$claudeDir}/*") ?: []);
            rmdir($claudeDir);
        }

        $this->artisan('commandments:install-hooks')
            ->assertSuccessful();

        $this->assertDirectoryExists($claudeDir);

        // Cleanup
        array_map('unlink', glob("{$claudeDir}/*") ?: []);
        rmdir($claudeDir);
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

        // Cleanup
        unlink($settingsFile);
        rmdir(base_path('.claude'));
    }

    public function test_install_hooks_creates_claude_md(): void
    {
        $claudeMdPath = base_path('CLAUDE.md');

        // Remove if exists
        if (file_exists($claudeMdPath)) {
            unlink($claudeMdPath);
        }

        $this->artisan('commandments:install-hooks')
            ->assertSuccessful();

        $this->assertFileExists($claudeMdPath);
        $this->assertStringContainsString('Code Commandments', file_get_contents($claudeMdPath));

        // Cleanup
        unlink($claudeMdPath);
        if (is_dir(base_path('.claude'))) {
            array_map('unlink', glob(base_path('.claude').'/*') ?: []);
            rmdir(base_path('.claude'));
        }
    }
}
