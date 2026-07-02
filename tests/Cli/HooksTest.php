<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Hooks;
use PHPUnit\Framework\TestCase;

/**
 * The hook wiring must converge to exactly our current set — one `remind` under `PostToolUse`, one
 * `judge-reminder` under `Stop`, one under `PreToolUse` matching `Bash` — from ANY starting state,
 * including a stale/duplicate copy under the wrong event, while never touching a hook it didn't write.
 * It runs on every `composer update` (via sync), so it has to be idempotent by content.
 */
final class HooksTest extends TestCase
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

    public function test_it_converges_to_the_current_set_and_cleans_stale_copies(): void
    {
        // A mid-migration mess: remind under the OLD event, an already-correct judge-reminder, a
        // duplicate judge-reminder, plus the project's own hook under two events.
        $this->write([
            'hooks' => [
                'UserPromptSubmit' => [
                    ['hooks' => [['type' => 'command', 'command' => 'my-own-hook']]],
                    ['hooks' => [['type' => 'command', 'command' => 'php vendor/bin/commandments remind']]],
                ],
                'Stop' => [
                    ['hooks' => [['type' => 'command', 'command' => 'php vendor/bin/commandments judge-reminder']]],
                    ['hooks' => [['type' => 'command', 'command' => 'php vendor/bin/commandments judge-reminder']]],
                    ['hooks' => [['type' => 'command', 'command' => 'keep-me-too']]],
                ],
            ],
        ]);

        Hooks::wire($this->path);

        $this->assertSame(0, $this->countMatching('UserPromptSubmit', 'remind'), 'the stale remind is gone');
        $this->assertSame(1, $this->countMatching('PostToolUse', 'remind'), 'exactly one remind, under PostToolUse');
        $this->assertSame(1, $this->countMatching('Stop', 'judge-reminder'), 'exactly one judge-reminder, under Stop');
        $this->assertSame(1, $this->countMatching('PreToolUse', 'judge-reminder'), 'exactly one judge-reminder, under PreToolUse');
        $this->assertContains('my-own-hook', $this->commands('UserPromptSubmit'), 'the project\'s own hook is untouched');
        $this->assertContains('keep-me-too', $this->commands('Stop'), 'a foreign hook under Stop is preserved');
    }

    public function test_it_is_idempotent_once_wired(): void
    {
        $this->assertTrue(Hooks::wire($this->path), 'first wire writes');
        $this->assertFalse(Hooks::wire($this->path), 'a second wire is a no-op');
        $this->assertSame(1, $this->countMatching('PostToolUse', 'remind'));
        $this->assertSame(1, $this->countMatching('Stop', 'judge-reminder'));
        $this->assertSame(1, $this->countMatching('PreToolUse', 'judge-reminder'));
    }

    public function test_the_pre_tool_use_hook_is_scoped_to_bash(): void
    {
        Hooks::wire($this->path);

        $settings = (array) json_decode((string) file_get_contents($this->path), true);
        $group = $settings['hooks']['PreToolUse'][0] ?? [];

        $this->assertSame('Bash', $group['matcher'] ?? null, 'PreToolUse is matched to Bash calls only');
    }

    /** @param array<string, mixed> $settings */
    private function write(array $settings): void
    {
        file_put_contents($this->path, json_encode($settings));
    }

    private function countMatching(string $event, string $subcommand): int
    {
        return count(array_filter(
            $this->commands($event),
            static fn (string $c): bool => str_contains($c, 'commandments') && str_contains($c, $subcommand),
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
