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
        // Simulate an OLD consumer whose COMMITTED settings.json predates both the
        // local-settings move and handoff-detect. reassert migrates our hooks OUT of
        // the committed file and writes the current set to the LOCAL settings.local.json.
        $committed = $this->dir . '/.claude/settings.json';
        $local = $this->dir . '/.claude/settings.local.json';
        file_put_contents($committed, json_encode([
            'hooks' => [
                'SessionStart' => [
                    ['hooks' => [['type' => 'command', 'command' => 'vendor/bin/commandments scripture 2>/dev/null || true']]],
                ],
            ],
        ]));

        $status = ClaudeHooksInstaller::reassert($this->dir, false);
        $this->assertSame(ClaudeHooksInstaller::STATUS_INSTALLED, $status);

        // Our hooks are GONE from the committed file (migrated out).
        $committedHooks = json_decode((string) file_get_contents($committed), true)['hooks'] ?? [];
        $this->assertNotContains('vendor/bin/commandments scripture 2>/dev/null || true', $this->commands($committedHooks, 'SessionStart'));

        // The current set lives in the local file: the new hook was added…
        $session = $this->commands(json_decode((string) file_get_contents($local), true)['hooks'], 'SessionStart');
        $this->assertContains('sh .claude/hooks/handoff-detect.sh 2>/dev/null || true', $session);
        // …without duplicating the pre-existing one.
        $this->assertSame(1, count(array_filter($session, fn ($c) => str_contains($c, 'scripture'))));
    }

    public function test_reassert_is_idempotent(): void
    {
        $file = $this->dir . '/.claude/settings.json';
        file_put_contents($file, json_encode(['hooks' => []]));

        $this->assertSame(ClaudeHooksInstaller::STATUS_INSTALLED, ClaudeHooksInstaller::reassert($this->dir, false));
        $this->assertSame(ClaudeHooksInstaller::STATUS_UNCHANGED, ClaudeHooksInstaller::reassert($this->dir, false));
    }

    public function test_reassert_preserves_user_added_hooks(): void
    {
        $file = $this->dir . '/.claude/settings.json';
        file_put_contents($file, json_encode([
            'hooks' => ['SessionStart' => [['hooks' => [['type' => 'command', 'command' => 'my-own-thing']]]]],
        ]));

        ClaudeHooksInstaller::reassert($this->dir, false);

        $session = $this->commands(json_decode((string) file_get_contents($file), true)['hooks'], 'SessionStart');
        $this->assertContains('my-own-thing', $session);
    }

    public function test_apply_is_idempotent_for_every_emitted_command(): void
    {
        // Ownership round-trip: apply() over its OWN previous output must add
        // nothing. If any script the build emits weren't recognized by
        // isOwnedCommand, re-applying would append a duplicate.
        // A Laravel project (artisan present) and a standalone one.
        $laravel = $this->dir . '/laravel';
        $standalone = $this->dir . '/standalone';
        mkdir($laravel, 0755, true);
        mkdir($standalone, 0755, true);
        touch($laravel . '/artisan');

        foreach ([$laravel, $standalone] as $base) {
            foreach ([false, true] as $planLoop) {
                $first = ClaudeHooksInstaller::apply([], $base, $planLoop);
                $second = ClaudeHooksInstaller::apply($first, $base, $planLoop);
                $this->assertSame($first, $second, 'apply() must be idempotent (every emitted command owned)');
            }
        }
    }

    public function test_runner_is_detected_from_the_project(): void
    {
        $laravel = $this->dir . '/laravel';
        mkdir($laravel, 0755, true);
        touch($laravel . '/artisan');

        $this->assertSame(ClaudeHooksInstaller::ARTISAN, ClaudeHooksInstaller::runnerFor($laravel));
        $this->assertSame(ClaudeHooksInstaller::STANDALONE, ClaudeHooksInstaller::runnerFor($this->dir));

        // A Laravel project's wiring uses artisan no matter which path applies it.
        $session = $this->commands(ClaudeHooksInstaller::apply([], $laravel, false), 'SessionStart');
        $this->assertContains('php artisan commandments:scripture 2>/dev/null || true', $session);
    }

    public function test_apply_replaces_a_stale_owned_command_without_duplicating(): void
    {
        // An old consumer has a renamed/old owned command; apply() drops it and
        // writes the current set — no stale leftover, no duplicate.
        $existing = [
            'SessionStart' => [
                ['hooks' => [['type' => 'command', 'command' => 'vendor/bin/commandments OLDsubcommand 2>/dev/null || true']]],
                ['hooks' => [['type' => 'command', 'command' => 'my-own-hook']]],
            ],
        ];

        $out = ClaudeHooksInstaller::apply($existing, $this->dir, false);
        $session = $this->commands($out, 'SessionStart');

        $this->assertNotContains('vendor/bin/commandments OLDsubcommand 2>/dev/null || true', $session, 'stale owned command must be dropped');
        $this->assertContains('my-own-hook', $session, 'consumer hook preserved');
        $this->assertContains('vendor/bin/commandments scripture 2>/dev/null || true', $session);
    }

    public function test_plan_loop_session_reset_is_wired_and_owned(): void
    {
        $out = ClaudeHooksInstaller::apply([], $this->dir, true);
        $session = $this->commands($out, 'SessionStart');
        $this->assertContains('sh .claude/hooks/plan-session-reset.sh', $session);
    }

    public function test_post_merge_hook_script_detects_runner(): void
    {
        $body = ClaudeHooksInstaller::postMergeHookScript();
        $this->assertStringContainsString('if [ -f artisan ]', $body);
        $this->assertStringContainsString('php artisan commandments:sync --after=previous', $body);
        $this->assertStringContainsString('vendor/bin/commandments sync --after=previous', $body);
    }

    public function test_reassert_skips_when_no_settings_file(): void
    {
        $this->assertSame(
            ClaudeHooksInstaller::STATUS_NO_SETTINGS,
            ClaudeHooksInstaller::reassert($this->dir, false),
        );
        $this->assertFileDoesNotExist($this->dir . '/.claude/settings.json');
        $this->assertFileDoesNotExist($this->dir . '/.claude/settings.local.json');
    }

    public function test_migrate_strips_our_entries_from_committed_keeping_consumer_entries(): void
    {
        // A consumer hook whose script name merely CONTAINS a retired one
        // (grind-keep-going.sh vs the retired keep-going.sh) must NOT be mistaken
        // for ours — only OUR runner/scripts are migrated out of the committed file.
        $committed = $this->dir . '/.claude/settings.json';
        file_put_contents($committed, json_encode([
            'permissions' => ['allow' => ['Bash(ls)']],
            'instructions' => 'This project uses Code Commandments to enforce coding standards.',
            'hooks' => [
                'SessionStart' => [
                    ['hooks' => [['type' => 'command', 'command' => 'vendor/bin/commandments scripture 2>/dev/null || true']]],
                ],
                'Stop' => [
                    ['hooks' => [['type' => 'command', 'command' => 'sh .claude/hooks/grind-keep-going.sh']]],
                ],
            ],
        ]));

        $this->assertTrue(ClaudeHooksInstaller::migrateCommittedSettings($this->dir));

        $after = json_decode((string) file_get_contents($committed), true);
        // Consumer keys + their look-alike hook survive.
        $this->assertArrayHasKey('permissions', $after);
        $this->assertSame('sh .claude/hooks/grind-keep-going.sh', $after['hooks']['Stop'][0]['hooks'][0]['command']);
        // OUR runner hook + seeded instructions are gone.
        $this->assertArrayNotHasKey('SessionStart', $after['hooks']);
        $this->assertArrayNotHasKey('instructions', $after);
    }
}
