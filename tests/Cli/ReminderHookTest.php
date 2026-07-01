<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\ReminderHook;
use PHPUnit\Framework\TestCase;

/**
 * The reminder hook wiring must converge to exactly one hook under `PostToolUse` from ANY starting
 * state — including a stale/duplicate copy under `UserPromptSubmit` — while never touching a hook it
 * didn't write. It runs on every `composer update` (via sync), so it has to be idempotent by content.
 */
final class ReminderHookTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/cc-hook-' . uniqid('', true) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_it_converges_to_one_post_tool_use_hook_and_cleans_a_stale_duplicate(): void
    {
        // The exact broken state a mid-migration project ended up in: remind under BOTH events,
        // plus the project's own UserPromptSubmit hook.
        $this->write([
            'hooks' => [
                'UserPromptSubmit' => [
                    ['hooks' => [['type' => 'command', 'command' => 'my-own-hook']]],
                    ['hooks' => [['type' => 'command', 'command' => 'php vendor/bin/commandments remind']]],
                ],
                'PostToolUse' => [['hooks' => [['type' => 'command', 'command' => ReminderHook::COMMAND]]]],
            ],
        ]);

        ReminderHook::wire($this->path);

        $this->assertSame(0, $this->remindCount('UserPromptSubmit'), 'the stale duplicate is gone');
        $this->assertSame(1, $this->remindCount('PostToolUse'), 'exactly one remind, under PostToolUse');
        $this->assertContains('my-own-hook', $this->commands('UserPromptSubmit'), 'the project\'s own hook is untouched');
    }

    public function test_it_is_idempotent_once_wired(): void
    {
        $this->assertTrue(ReminderHook::wire($this->path), 'first wire writes');
        $this->assertFalse(ReminderHook::wire($this->path), 'a second wire is a no-op');
        $this->assertSame(1, $this->remindCount('PostToolUse'));
    }

    /** @param array<string, mixed> $settings */
    private function write(array $settings): void
    {
        file_put_contents($this->path, json_encode($settings));
    }

    private function remindCount(string $event): int
    {
        return count(array_filter(
            $this->commands($event),
            static fn (string $c): bool => str_contains($c, 'commandments') && str_contains($c, 'remind'),
        ));
    }

    /** @return list<string> */
    private function commands(string $event): array
    {
        $settings = (array) json_decode((string) file_get_contents($this->path), true);
        $commands = [];

        foreach ((array) ($settings['hooks'][$event] ?? []) as $group) {
            foreach ((array) ($group['hooks'] ?? []) as $hook) {
                $commands[] = (string) ($hook['command'] ?? '');
            }
        }

        return $commands;
    }
}
