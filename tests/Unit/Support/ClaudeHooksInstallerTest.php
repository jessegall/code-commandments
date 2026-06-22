<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\ClaudeHooksInstaller;
use PHPUnit\Framework\TestCase;

class ClaudeHooksInstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-hooks-' . uniqid();
        mkdir($this->dir . '/.claude', 0755, true);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function commands(array $hooks, string $event): array
    {
        $out = [];
        foreach ($hooks[$event] ?? [] as $group) {
            foreach ($group['hooks'] ?? [] as $h) {
                $out[] = $h['command'] ?? '';
            }
        }

        return $out;
    }

    public function test_artisan_variant_uses_colon_subcommands(): void
    {
        $hooks = ClaudeHooksInstaller::build(...[...ClaudeHooksInstaller::ARTISAN, false]);
        $session = $this->commands($hooks, 'SessionStart');

        $this->assertContains('php artisan commandments:scripture 2>/dev/null || true', $session);
        $this->assertContains('php artisan commandments:reports --check 2>/dev/null || true', $session);
        $this->assertContains('sh .claude/hooks/handoff-detect.sh 2>/dev/null || true', $session);
        $this->assertSame(['php artisan commandments:judge --git 2>/dev/null; exit 0'], $this->commands($hooks, 'Stop'));
    }

    public function test_standalone_variant_uses_space_subcommands(): void
    {
        $hooks = ClaudeHooksInstaller::build(...[...ClaudeHooksInstaller::STANDALONE, false]);
        $session = $this->commands($hooks, 'SessionStart');

        $this->assertContains('vendor/bin/commandments scripture 2>/dev/null || true', $session);
        $this->assertContains('sh .claude/hooks/handoff-detect.sh 2>/dev/null || true', $session);
        $this->assertSame(['vendor/bin/commandments judge --git 2>/dev/null; exit 0'], $this->commands($hooks, 'Stop'));
    }

    public function test_plan_loop_adds_pretooluse_when_enabled(): void
    {
        $off = ClaudeHooksInstaller::build(...[...ClaudeHooksInstaller::ARTISAN, false]);
        $on = ClaudeHooksInstaller::build(...[...ClaudeHooksInstaller::ARTISAN, true]);

        $this->assertArrayNotHasKey('PreToolUse', $off);
        $this->assertArrayHasKey('PreToolUse', $on);
    }

    public function test_reassert_adds_a_missing_hook_to_an_existing_settings_file(): void
    {
        // Simulate an OLD consumer whose settings.json predates handoff-detect.
        $file = $this->dir . '/.claude/settings.json';
        file_put_contents($file, json_encode([
            'hooks' => [
                'SessionStart' => [
                    ['hooks' => [['type' => 'command', 'command' => 'vendor/bin/commandments scripture 2>/dev/null || true']]],
                ],
            ],
        ]));

        $status = ClaudeHooksInstaller::reassert($this->dir, ClaudeHooksInstaller::STANDALONE, false);
        $this->assertSame(ClaudeHooksInstaller::STATUS_INSTALLED, $status);

        $hooks = json_decode((string) file_get_contents($file), true)['hooks'];
        $session = $this->commands($hooks, 'SessionStart');
        // The new hook was added…
        $this->assertContains('sh .claude/hooks/handoff-detect.sh 2>/dev/null || true', $session);
        // …without duplicating the pre-existing one.
        $this->assertSame(1, count(array_filter($session, fn ($c) => str_contains($c, 'scripture'))));
    }

    public function test_reassert_is_idempotent(): void
    {
        $file = $this->dir . '/.claude/settings.json';
        file_put_contents($file, json_encode(['hooks' => []]));

        $this->assertSame(ClaudeHooksInstaller::STATUS_INSTALLED, ClaudeHooksInstaller::reassert($this->dir, ClaudeHooksInstaller::STANDALONE, false));
        $this->assertSame(ClaudeHooksInstaller::STATUS_UNCHANGED, ClaudeHooksInstaller::reassert($this->dir, ClaudeHooksInstaller::STANDALONE, false));
    }

    public function test_reassert_preserves_user_added_hooks(): void
    {
        $file = $this->dir . '/.claude/settings.json';
        file_put_contents($file, json_encode([
            'hooks' => ['SessionStart' => [['hooks' => [['type' => 'command', 'command' => 'my-own-thing']]]]],
        ]));

        ClaudeHooksInstaller::reassert($this->dir, ClaudeHooksInstaller::STANDALONE, false);

        $session = $this->commands(json_decode((string) file_get_contents($file), true)['hooks'], 'SessionStart');
        $this->assertContains('my-own-thing', $session);
    }

    public function test_reassert_skips_when_no_settings_file(): void
    {
        $this->assertSame(
            ClaudeHooksInstaller::STATUS_NO_SETTINGS,
            ClaudeHooksInstaller::reassert($this->dir, ClaudeHooksInstaller::STANDALONE, false),
        );
        $this->assertFileDoesNotExist($this->dir . '/.claude/settings.json');
    }
}
