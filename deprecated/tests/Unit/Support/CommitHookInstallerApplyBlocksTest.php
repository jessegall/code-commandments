<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\CommitHookInstaller;
use PHPUnit\Framework\TestCase;

class CommitHookInstallerApplyBlocksTest extends TestCase
{
    private string $dir;

    /** @var list<string> */
    private array $emitted = [];

    /** @var list<string> */
    private array $errored = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-blocks-' . uniqid();
        mkdir($this->dir . '/.git/hooks', 0755, true);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function apply(array $blocks): void
    {
        $this->emitted = [];
        $this->errored = [];
        (new CommitHookInstaller())->applyBlocks(
            $this->dir,
            $blocks,
            function (string $l): void {
                $this->emitted[] = $l;
            },
            function (string $l): void {
                $this->errored[] = $l;
            },
        );
    }

    private function hook(string $name): string
    {
        return (string) @file_get_contents($this->dir . '/.git/hooks/' . $name);
    }

    public function test_installs_requested_blocks_and_reports_them_on_disk(): void
    {
        $this->apply([
            CommitHookInstaller::BLOCK_PRE_COMMIT_GATE,
            CommitHookInstaller::BLOCK_POST_COMMIT_RESET,
        ]);

        $this->assertStringContainsString('pre-commit gate', $this->hook('pre-commit'));
        $this->assertStringContainsString('post-commit reset', $this->hook('post-commit'));

        $installed = (new CommitHookInstaller())->installedBlocks($this->dir);
        $this->assertContains(CommitHookInstaller::BLOCK_PRE_COMMIT_GATE, $installed);
        $this->assertContains(CommitHookInstaller::BLOCK_POST_COMMIT_RESET, $installed);
    }

    public function test_pre_push_holds_two_blocks_gate_before_reset(): void
    {
        $this->apply([
            CommitHookInstaller::BLOCK_PRE_PUSH_GATE,
            CommitHookInstaller::BLOCK_PRE_PUSH_RESET,
        ]);

        $prePush = $this->hook('pre-push');
        $this->assertLessThan(strpos($prePush, 'pre-push reset'), strpos($prePush, 'pre-push gate'));
    }

    public function test_pre_push_gate_probes_via_gate_probe_and_consumes_a_completed_walk(): void
    {
        $this->apply([CommitHookInstaller::BLOCK_PRE_PUSH_GATE]);

        $prePush = $this->hook('pre-push');

        // The probe uses --gate-probe (works mid-pilgrimage, exit code only) — never a
        // bare `judge` (which the lock would refuse) nor the retired bypass env.
        $this->assertStringContainsString('judge --gate-probe', $prePush);
        $this->assertStringNotContainsString('COMMANDMENTS_PILGRIMAGE_BYPASS', $prePush);

        // A completed walk earns ONE push, then is consumed so the next push re-arms.
        $this->assertStringContainsString('pilgrimage --is-complete', $prePush);
        $this->assertStringContainsString('pilgrimage --clear', $prePush);

        // It must NOT teach the owning agent session to escape with --no-verify.
        $this->assertStringNotContainsString('--no-verify', $prePush);
    }

    public function test_removing_one_of_two_pre_push_blocks_keeps_the_other(): void
    {
        $this->apply([CommitHookInstaller::BLOCK_PRE_PUSH_GATE, CommitHookInstaller::BLOCK_PRE_PUSH_RESET]);
        $this->apply([CommitHookInstaller::BLOCK_PRE_PUSH_RESET]);

        $this->assertFileExists($this->dir . '/.git/hooks/pre-push');
        $this->assertStringNotContainsString('pre-push gate', $this->hook('pre-push'));
        $this->assertStringContainsString('pre-push reset', $this->hook('pre-push'));
    }

    public function test_empty_set_removes_a_hook_with_only_our_blocks(): void
    {
        $this->apply([CommitHookInstaller::BLOCK_PRE_COMMIT_GATE]);
        $this->assertFileExists($this->dir . '/.git/hooks/pre-commit');

        $this->apply([]);
        $this->assertFileDoesNotExist($this->dir . '/.git/hooks/pre-commit');
    }

    public function test_strips_our_block_but_preserves_a_foreign_hook_body(): void
    {
        file_put_contents($this->dir . '/.git/hooks/pre-commit', "#!/usr/bin/env sh\necho \"theirs\"\n");

        $this->apply([CommitHookInstaller::BLOCK_PRE_COMMIT_GATE]);
        $this->assertStringContainsString('theirs', $this->hook('pre-commit'));
        $this->assertStringContainsString('pre-commit gate', $this->hook('pre-commit'));

        $this->apply([]);
        $this->assertFileExists($this->dir . '/.git/hooks/pre-commit');
        $this->assertStringContainsString('theirs', $this->hook('pre-commit'));
        $this->assertStringNotContainsString('pre-commit gate', $this->hook('pre-commit'));
    }

    public function test_not_a_git_repo_errors_only_when_blocks_requested(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir . '/.git'));

        $this->apply([CommitHookInstaller::BLOCK_PRE_COMMIT_GATE]);
        $this->assertNotEmpty($this->errored);

        $this->apply([]);
        $this->assertEmpty($this->errored);
    }

    public function test_warns_when_hooks_path_is_redirected(): void
    {
        // Make it a real git repo and redirect hooks away from .git/hooks (husky-style).
        shell_exec('git -C ' . escapeshellarg($this->dir) . ' init -q 2>/dev/null');
        mkdir($this->dir . '/.husky', 0755, true);
        shell_exec('git -C ' . escapeshellarg($this->dir) . ' config core.hooksPath .husky 2>/dev/null');

        $this->apply([CommitHookInstaller::BLOCK_PRE_COMMIT_GATE]);

        $this->assertNotEmpty($this->errored);
        $this->assertStringContainsString('core.hooksPath', implode("\n", $this->errored));
    }
}
