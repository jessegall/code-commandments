<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support\Profiles;

use JesseGall\CodeCommandments\Support\ClaudeMdInstaller;
use JesseGall\CodeCommandments\Support\Profiles\JudgeScope;
use JesseGall\CodeCommandments\Support\Profiles\ProfileService;
use PHPUnit\Framework\TestCase;

class ProfileServiceTest extends TestCase
{
    private string $dir;

    /** @var list<string> */
    private array $errors = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-profile-' . uniqid();
        mkdir($this->dir, 0755, true);
        shell_exec('git -C ' . escapeshellarg($this->dir) . ' init -q 2>/dev/null');
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function service(): ProfileService
    {
        return new ProfileService($this->dir);
    }

    private function switch(string $name): void
    {
        $this->errors = [];
        $this->service()->switch(
            $name,
            static fn (string $l) => null,
            function (string $l): void {
                $this->errors[] = $l;
            },
        );
    }

    private function hook(string $name): string
    {
        return (string) @file_get_contents($this->dir . '/.git/hooks/' . $name);
    }

    private function settingsEvents(): array
    {
        $settings = json_decode((string) @file_get_contents($this->dir . '/.claude/settings.json'), true);

        return is_array($settings) ? array_keys($settings['hooks'] ?? []) : [];
    }

    private function claudeMd(): string
    {
        return (string) @file_get_contents($this->dir . '/CLAUDE.md');
    }

    // --- switching installs the right bundle ---

    public function test_phased_installs_precommit_gate_and_briefing_and_state(): void
    {
        $this->switch('phased');

        $this->assertStringContainsString('pre-commit gate', $this->hook('pre-commit'));
        $this->assertStringContainsString('post-commit reset', $this->hook('post-commit'));
        $this->assertStringContainsString('commit-msg guard', $this->hook('commit-msg'));
        $this->assertStringNotContainsString('pre-push gate', $this->hook('pre-push'));
        $this->assertStringContainsString('pre-push reset', $this->hook('pre-push'));
        // Briefing is hook-delivered now — no committed CLAUDE.md section for ANY profile.
        $this->assertStringNotContainsString(ClaudeMdInstaller::BEGIN, $this->claudeMd());
        $this->assertSame('phased', trim((string) file_get_contents($this->dir . '/.commandments/profile')));
        $this->assertContains('Stop', $this->settingsEvents());
        $this->assertContains('PostToolUse', $this->settingsEvents());
    }

    public function test_grind_installs_prepush_gate_before_reset_and_no_precommit(): void
    {
        $this->switch('grind');

        $prePush = $this->hook('pre-push');
        $this->assertStringContainsString('pre-push gate', $prePush);
        $this->assertStringContainsString('pre-push reset', $prePush);
        // Gate must come BEFORE reset so the judge runs with until-push absolutions live.
        $this->assertLessThan(strpos($prePush, 'pre-push reset'), strpos($prePush, 'pre-push gate'));
        // No per-phase machinery.
        $this->assertFileDoesNotExist($this->dir . '/.git/hooks/pre-commit');
        $this->assertFileDoesNotExist($this->dir . '/.git/hooks/post-commit');
        // grind keeps going (Stop = keep-going hook) and the script is installed.
        $this->assertContains('Stop', $this->settingsEvents());
        $this->assertFileExists($this->dir . '/.claude/hooks/profile-keep-going.sh');
        $this->assertContains('SessionStart', $this->settingsEvents());
        $this->assertContains('UserPromptSubmit', $this->settingsEvents());
    }

    // --- teardown is computed from on-disk blocks ---

    public function test_grind_to_phased_strips_prepush_gate_and_adds_precommit(): void
    {
        $this->switch('grind');
        $this->switch('phased');

        $this->assertStringContainsString('pre-commit gate', $this->hook('pre-commit'));
        $this->assertStringNotContainsString('pre-push gate', $this->hook('pre-push'));
        $this->assertStringContainsString('pre-push reset', $this->hook('pre-push'));
    }

    public function test_disabled_removes_all_hooks_and_claudemd_section(): void
    {
        $this->switch('phased');
        $this->switch('disabled');

        $this->assertFileDoesNotExist($this->dir . '/.git/hooks/pre-commit');
        $this->assertFileDoesNotExist($this->dir . '/.git/hooks/post-commit');
        $this->assertFileDoesNotExist($this->dir . '/.git/hooks/pre-push');
        $this->assertFileDoesNotExist($this->dir . '/.git/hooks/commit-msg');
        $this->assertStringNotContainsString(ClaudeMdInstaller::BEGIN, $this->claudeMd());
        $this->assertStringNotContainsString('## Code Commandments', $this->claudeMd());
        // No package-owned hooks remain in settings.json.
        $this->assertSame([], $this->settingsEvents());
    }

    public function test_teardown_preserves_a_foreign_hook_body(): void
    {
        @mkdir($this->dir . '/.git/hooks', 0755, true);
        file_put_contents($this->dir . '/.git/hooks/pre-commit', "#!/usr/bin/env sh\necho \"my own hook\"\n");

        $this->switch('phased');
        $this->assertStringContainsString('my own hook', $this->hook('pre-commit'));
        $this->assertStringContainsString('pre-commit gate', $this->hook('pre-commit'));

        $this->switch('disabled');
        // Our block stripped, the consumer's body kept (file not deleted).
        $this->assertFileExists($this->dir . '/.git/hooks/pre-commit');
        $this->assertStringContainsString('my own hook', $this->hook('pre-commit'));
        $this->assertStringNotContainsString('pre-commit gate', $this->hook('pre-commit'));
    }

    // --- CLAUDE.md is cleaned for every profile (briefing is hook-delivered) ---

    public function test_phased_strips_a_legacy_claudemd_section_and_keeps_the_rest(): void
    {
        file_put_contents(
            $this->dir . '/CLAUDE.md',
            "# My App\n\nIntro.\n\n" . ClaudeMdInstaller::BEGIN . "\n## Code Commandments\nold\n" . ClaudeMdInstaller::END . "\n\n## Keep\nmine\n",
        );

        $this->switch('phased');

        $md = $this->claudeMd();
        $this->assertStringNotContainsString(ClaudeMdInstaller::BEGIN, $md);
        $this->assertStringNotContainsString('## Code Commandments', $md);
        $this->assertStringContainsString('# My App', $md);
        $this->assertStringContainsString('## Keep', $md);
    }

    public function test_reassert_cleans_a_legacy_claudemd_section_on_update(): void
    {
        // Legacy consumer: a committed CLAUDE.md section + no profile state.
        file_put_contents($this->dir . '/CLAUDE.md', ClaudeMdInstaller::BEGIN . "\nold\n" . ClaudeMdInstaller::END . "\n");
        @mkdir($this->dir . '/.git/hooks', 0755, true);
        file_put_contents($this->dir . '/.git/hooks/pre-commit', "#!/usr/bin/env sh\n# >>> code-commandments pre-commit gate >>>\n# <<< code-commandments pre-commit gate <<<\n");

        $this->service()->reassert(static fn ($l) => null, static fn ($l) => null);

        $this->assertStringNotContainsString(ClaudeMdInstaller::BEGIN, $this->claudeMd());
        // State persisted to phased BEFORE the marker was stripped.
        $this->assertSame('phased', trim((string) file_get_contents($this->dir . '/.commandments/profile')));
    }

    // --- switching never touches absolutions ---

    public function test_switch_does_not_touch_the_confession_tablet(): void
    {
        mkdir($this->dir . '/.commandments', 0755, true);
        file_put_contents($this->dir . '/.commandments/confessions.json', '{"sentinel":true}');

        $this->switch('phased');
        $this->switch('grind');
        $this->switch('disabled');

        $this->assertSame('{"sentinel":true}', file_get_contents($this->dir . '/.commandments/confessions.json'));
    }

    // --- migration / inference ---

    public function test_active_defaults_to_disabled_without_markers(): void
    {
        $this->assertSame('disabled', $this->service()->active()->name());
    }

    public function test_active_infers_phased_from_legacy_claudemd_marker(): void
    {
        file_put_contents($this->dir . '/CLAUDE.md', "# Title\n\n" . ClaudeMdInstaller::BEGIN . "\nx\n" . ClaudeMdInstaller::END . "\n");

        $this->assertSame('phased', $this->service()->active()->name());
    }

    public function test_active_infers_phased_from_legacy_precommit_gate(): void
    {
        @mkdir($this->dir . '/.git/hooks', 0755, true);
        file_put_contents($this->dir . '/.git/hooks/pre-commit', "#!/usr/bin/env sh\n# >>> code-commandments pre-commit gate >>>\n# <<< code-commandments pre-commit gate <<<\n");

        $this->assertSame('phased', $this->service()->active()->name());
    }

    public function test_explicit_scope_is_null_for_legacy_consumer_without_state(): void
    {
        file_put_contents($this->dir . '/CLAUDE.md', ClaudeMdInstaller::BEGIN . "\nx\n" . ClaudeMdInstaller::END . "\n");

        // Inference makes it phased for HOOKS, but a bare judge keeps full-scan
        // (null) until the consumer explicitly selects a profile.
        $this->assertSame('phased', $this->service()->active()->name());
        $this->assertNull(ProfileService::explicitScope($this->dir));
    }

    public function test_explicit_scope_follows_a_selected_profile(): void
    {
        $this->switch('grind');
        $this->assertSame(JudgeScope::Branch, ProfileService::explicitScope($this->dir));

        $this->switch('phased');
        $this->assertSame(JudgeScope::Staged, ProfileService::explicitScope($this->dir));
    }

    // --- mid-session re-index (drift) ---

    public function test_drift_check_fires_once_after_a_change_then_is_silent(): void
    {
        $this->switch('grind');

        $first = $this->driftLines();
        $this->assertNotEmpty($first);
        $this->assertStringContainsString('grind', implode("\n", $first));

        $this->assertSame([], $this->driftLines(), 'drift-check must be silent once briefed');
    }

    public function test_drift_check_refires_after_switching_profiles(): void
    {
        $this->switch('grind');
        $this->driftLines(); // settle to grind

        $this->switch('phased');
        $lines = $this->driftLines();
        $this->assertNotEmpty($lines);
        $this->assertStringContainsString('phased', implode("\n", $lines));
    }

    public function test_drift_check_stays_silent_for_a_disabled_profile(): void
    {
        $this->switch('disabled');
        $this->assertSame([], $this->driftLines());
    }

    /** @return list<string> */
    private function driftLines(): array
    {
        $lines = [];
        $this->service()->driftCheck(function (string $l) use (&$lines): void {
            $lines[] = $l;
        });

        return $lines;
    }

    public function test_reassert_persists_inferred_phased_and_removes_zero_hooks(): void
    {
        // A legacy consumer: pre-commit gate present, no profile state.
        $this->switch('phased');
        @unlink($this->dir . '/.commandments/profile');
        $before = $this->hook('pre-commit');

        $this->service()->reassert(static fn ($l) => null, static fn ($l) => null);

        $this->assertSame('phased', trim((string) file_get_contents($this->dir . '/.commandments/profile')));
        $this->assertStringContainsString('pre-commit gate', $this->hook('pre-commit'));
        $this->assertSame($before, $this->hook('pre-commit'));
    }
}
