<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Install;
use JesseGall\CodeCommandments\Hooks\HookRegistry;
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
        // `install` runs `sync`, which publishes a whole skill tree in here — deleting the handful
        // of files this test writes itself left every run's temp directory behind.
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_it_refuses_a_composer_json_it_cannot_read_rather_than_replace_it(): void
    {
        $manifest = "\xEF\xBB\xBF{\n  \"require\": {\"vendor/thing\": \"^1.0\"}\n}\n";
        file_put_contents($this->dir . '/composer.json', $manifest);

        ob_start();
        $status = new Install()->run(Input::of('install'));
        ob_get_clean();

        $this->assertSame(2, $status);
        $this->assertSame($manifest, file_get_contents($this->dir . '/composer.json'), 'an unreadable manifest is left exactly as it was');
    }

    public function test_it_refuses_settings_json_it_cannot_read_rather_than_replace_it(): void
    {
        $settings = "{\n  // a comment makes this unreadable to json_decode\n  \"permissions\": {}\n}\n";
        file_put_contents($this->dir . '/.claude/settings.json', $settings);

        $this->assertFalse(HookRegistry::wire($this->dir));
        $this->assertSame($settings, file_get_contents($this->dir . '/.claude/settings.json'), "the user's settings survive");
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
        $this->assertTrue($this->hasDispatcher($settings['hooks']['PostToolUse'] ?? []), 'our hook dispatcher is wired under PostToolUse');
    }

    public function test_it_strips_our_old_user_prompt_submit_remind_and_wires_post_tool_use(): void
    {
        $this->settings([
            'hooks' => [
                'UserPromptSubmit' => [['hooks' => [['type' => 'command', 'command' => 'php vendor/bin/commandments remind']]]],
            ],
        ]);

        $this->install();

        $settings = $this->readSettings();

        $commands = array_column(array_merge(...array_column($settings['hooks']['UserPromptSubmit'] ?? [], 'hooks')), 'command');

        $this->assertNotContains('php vendor/bin/commandments remind', $commands, 'the old per-command remind is gone');
        $this->assertArrayNotHasKey('UserPromptSubmit', $settings['hooks'], 'and the event, emptied, is not left behind — no builtin binds there');
        $this->assertTrue($this->hasDispatcher($settings['hooks']['PostToolUse'] ?? []), 'the dispatcher is wired under PostToolUse');
    }

    /** @param array<string, mixed> $settings */
    private function settings(array $settings): void
    {
        file_put_contents($this->dir . '/.claude/settings.json', json_encode($settings));
    }

    private function install(): void
    {
        ob_start();
        new Install()->run(Input::of('install'));
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

    /** @param list<mixed> $groups  Is our stamped `commandments hooks` dispatcher wired in these groups? */
    private function hasDispatcher(array $groups): bool
    {
        foreach ($groups as $group) {
            foreach ((array) ($group['hooks'] ?? []) as $hook) {
                $command = (string) ($hook['command'] ?? '');

                if (str_contains($command, '@code-commandments-managed') && str_contains($command, ' hooks ')) {
                    return true;
                }
            }
        }

        return false;
    }
}
