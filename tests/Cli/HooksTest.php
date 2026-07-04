<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Hooks;
use PHPUnit\Framework\TestCase;

/**
 * The hook wiring converges to ONE stamped dispatcher entry per MOMENT — the events its handlers'
 * `bindings()` ask for — from any starting state, stripping our stale/old-format copies while never
 * touching a hook it didn't write. It runs on every `composer update` (via sync), so it's idempotent
 * by content, and a new same-moment handler never grows the file.
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

    public function test_it_wires_one_dispatcher_per_moment(): void
    {
        Hooks::wire($this->path);

        // The builtins bind PostToolUse, Stop, and PreToolUse — one dispatcher entry each, no more.
        $this->assertSame(1, $this->dispatchers('PostToolUse'));
        $this->assertSame(1, $this->dispatchers('Stop'));
        $this->assertSame(1, $this->dispatchers('PreToolUse'));
    }

    public function test_it_converges_and_strips_our_old_entries_while_keeping_foreign_ones(): void
    {
        // A migration mess: a pre-stamp `remind` under the OLD event, an old-format stamped per-class
        // entry, a duplicate, and two hooks the human wrote.
        $this->write([
            'hooks' => [
                'UserPromptSubmit' => [
                    ['hooks' => [['type' => 'command', 'command' => 'my-own-hook']]],
                    ['hooks' => [['type' => 'command', 'command' => 'php vendor/bin/commandments remind']]],
                ],
                'Stop' => [
                    ['hooks' => [['type' => 'command', 'command' => "php \"\$CLAUDE_PROJECT_DIR/vendor/bin/commandments\" hook 'JesseGall\\CodeCommandments\\Cli\\JudgeReminder' # @code-commandments-managed"]]],
                    ['hooks' => [['type' => 'command', 'command' => 'keep-me-too']]],
                ],
            ],
        ]);

        Hooks::wire($this->path);

        $this->assertSame(0, $this->dispatchers('UserPromptSubmit'), 'the stale pre-stamp remind is gone');
        $this->assertContains('my-own-hook', $this->commands('UserPromptSubmit'), "the human's own hook is untouched");
        $this->assertSame(1, $this->dispatchers('Stop'), 'the old per-class stamped entry is replaced by one dispatcher');
        $this->assertContains('keep-me-too', $this->commands('Stop'), 'a foreign hook under Stop is preserved');
    }

    public function test_it_is_idempotent_once_wired(): void
    {
        $this->assertTrue(Hooks::wire($this->path), 'first wire writes');
        $this->assertFalse(Hooks::wire($this->path), 'a second wire is a no-op');
        $this->assertSame(1, $this->dispatchers('PostToolUse'));
        $this->assertSame(1, $this->dispatchers('Stop'));
        $this->assertSame(1, $this->dispatchers('PreToolUse'));
    }

    public function test_a_consumer_registered_hook_adds_its_moment(): void
    {
        // FakeHook binds a new event (Notification) — a moment no builtin uses, so it gets its own entry.
        Hooks::wire($this->path, [...Hooks::BUILTINS, FakeHook::class]);

        $this->assertSame(1, $this->dispatchers('Notification'), 'the consumer hook adds its moment');
        $this->assertSame(1, $this->dispatchers('PostToolUse'), 'the builtins are still wired');
    }

    public function test_a_users_own_commandments_hook_is_never_stripped(): void
    {
        // A human wired their OWN hook that runs `commandments judge` — no stamp, ends in `judge`, not one
        // of our reminder subcommands. Wiring preserves it, in its own event.
        $ownHook = 'php vendor/bin/commandments judge --changes';
        $this->write(['hooks' => ['PostToolUse' => [['hooks' => [['type' => 'command', 'command' => $ownHook]]]]]]);

        Hooks::wire($this->path);

        $this->assertContains($ownHook, $this->commands('PostToolUse'), "the user's own commandments hook survives");
    }

    public function test_post_tool_use_is_one_unmatched_entry_but_pre_tool_use_is_scoped_to_bash(): void
    {
        Hooks::wire($this->path);

        $settings = (array) json_decode((string) file_get_contents($this->path), true);

        // PostToolUse mixes matchers (Remind unmatched, PlanReminder/ExitPlanMode) → one UNMATCHED entry;
        // the handlers self-filter by tool inside the dispatcher.
        $post = $settings['hooks']['PostToolUse'][0] ?? [];
        $this->assertArrayNotHasKey('matcher', $post, 'PostToolUse is unmatched — the dispatcher filters by tool');

        // PreToolUse handlers all share Bash → the entry keeps the Bash matcher (perf).
        $pre = $settings['hooks']['PreToolUse'][0] ?? [];
        $this->assertSame('Bash', $pre['matcher'] ?? null, 'PreToolUse is matched to Bash calls only');
    }

    /** @param array<string, mixed> $settings */
    private function write(array $settings): void
    {
        file_put_contents($this->path, json_encode($settings));
    }

    /** How many stamped dispatcher (`commandments hooks`) entries are wired under $event. */
    private function dispatchers(string $event): int
    {
        return count(array_filter(
            $this->commands($event),
            static fn (string $c): bool => str_contains($c, '@code-commandments-managed') && str_contains($c, ' hooks '),
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
