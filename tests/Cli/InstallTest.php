<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Install;
use PHPUnit\Framework\TestCase;

/**
 * `install` wires the reminder hook without ever disturbing a hook it didn't write. It runs
 * against the cwd, so each test runs inside a throwaway project directory.
 */
final class InstallTest extends TestCase
{
    private string $dir;

    private string $cwd;

    protected function setUp(): void
    {
        $this->cwd = (string) getcwd();
        $this->dir = sys_get_temp_dir() . '/cc-install-' . uniqid('', true);
        mkdir($this->dir . '/.claude', 0777, true);
        file_put_contents($this->dir . '/composer.json', "{\n}\n");
        chdir($this->dir);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        @unlink($this->dir . '/.claude/settings.json');
        @rmdir($this->dir . '/.claude');
        @unlink($this->dir . '/composer.json');
        @array_map('unlink', glob($this->dir . '/.commandments/*') ?: []);
        @rmdir($this->dir . '/.commandments');
        @rmdir($this->dir);
    }

    public function test_it_keeps_the_projects_own_hooks_and_wires_remind_under_post_tool_use(): void
    {
        $this->settings([
            'hooks' => [
                'UserPromptSubmit' => [['hooks' => [['type' => 'command', 'command' => 'my-custom-thing']]]],
                'PreToolUse' => [['hooks' => [['type' => 'command', 'command' => 'user-guard']]]],
            ],
        ]);

        $this->install();

        $settings = $this->readSettings();
        $commands = $this->commandsIn($settings);

        $this->assertContains('my-custom-thing', $commands, "a project's own UserPromptSubmit hook is untouched");
        $this->assertContains('user-guard', $commands, "a project's own PreToolUse hook is untouched");
        $this->assertTrue($this->hasRemind($settings['hooks']['PostToolUse'] ?? []), 'our remind is wired under PostToolUse');
    }

    public function test_it_migrates_our_old_user_prompt_submit_remind_to_post_tool_use(): void
    {
        $this->settings([
            'hooks' => [
                'UserPromptSubmit' => [['hooks' => [['type' => 'command', 'command' => 'php vendor/bin/commandments remind']]]],
            ],
        ]);

        $this->install();

        $settings = $this->readSettings();

        $this->assertArrayNotHasKey('UserPromptSubmit', $settings['hooks'], 'the old remind event is gone (it held only our hook)');
        $this->assertTrue($this->hasRemind($settings['hooks']['PostToolUse'] ?? []), 'remind moved to PostToolUse');
    }

    /** @param array<string, mixed> $settings */
    private function settings(array $settings): void
    {
        file_put_contents($this->dir . '/.claude/settings.json', json_encode($settings));
    }

    private function install(): void
    {
        ob_start();
        new Install()->run([]);
        ob_get_clean();
    }

    /** @return array<string, mixed> */
    private function readSettings(): array
    {
        return (array) json_decode((string) file_get_contents($this->dir . '/.claude/settings.json'), true);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function commandsIn(array $settings): array
    {
        $commands = [];

        foreach ((array) ($settings['hooks'] ?? []) as $groups) {
            foreach ((array) $groups as $group) {
                foreach ((array) ($group['hooks'] ?? []) as $hook) {
                    $commands[] = (string) ($hook['command'] ?? '');
                }
            }
        }

        return $commands;
    }

    /** @param list<mixed> $groups */
    private function hasRemind(array $groups): bool
    {
        foreach ($groups as $group) {
            foreach ((array) ($group['hooks'] ?? []) as $hook) {
                if (str_contains((string) ($hook['command'] ?? ''), "'" . \JesseGall\CodeCommandments\Cli\Remind::class . "'")) {
                    return true;
                }
            }
        }

        return false;
    }
}
