<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\CommitHookInstaller;
use JesseGall\CodeCommandments\Tests\TestCase;

class CommitHookInstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-commit-hook-' . uniqid();
        mkdir($this->dir . '/.git', 0755, true);

        // The hooks no-op unless the firing worktree opted into commandments (its
        // own .commandments/profile, not 'disabled') — so the tests run them with
        // the dir as cwd and a profile present. @see runHook().
        mkdir($this->dir . '/.commandments', 0755, true);
        file_put_contents($this->dir . '/.commandments/profile', "phased\n");
    }

    /**
     * Run a hook with the test dir as cwd (the worktree the guard reads), returning
     * its exit code.
     */
    private function runHook(string $hookPath, string ...$args): int
    {
        $cmd = 'cd ' . escapeshellarg($this->dir) . ' && sh ' . escapeshellarg($hookPath);

        foreach ($args as $arg) {
            $cmd .= ' ' . escapeshellarg($arg);
        }

        $out = [];
        exec($cmd . ' 2>/dev/null', $out, $status);

        return $status;
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/.git/hooks/pre-commit');
        @unlink($this->dir . '/.git/hooks/post-commit');
        @unlink($this->dir . '/.git/hooks/commit-msg');
        @rmdir($this->dir . '/.git/hooks');
        @rmdir($this->dir . '/.git');
        @unlink($this->dir . '/.commandments/profile');
        @rmdir($this->dir . '/.commandments');
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_installs_a_post_commit_reset_hook(): void
    {
        $status = (new CommitHookInstaller())->installPostCommit($this->dir);

        $this->assertSame(CommitHookInstaller::STATUS_INSTALLED, $status);

        $hook = file_get_contents($this->dir . '/.git/hooks/post-commit');
        $this->assertStringContainsString('absolve --clear', $hook);
        $this->assertStringContainsString('post-commit reset', $hook);
        $this->assertTrue(is_executable($this->dir . '/.git/hooks/post-commit'));
    }

    public function test_pre_and_post_commit_hooks_are_independent(): void
    {
        $installer = new CommitHookInstaller();

        $this->assertSame(CommitHookInstaller::STATUS_INSTALLED, $installer->install($this->dir));
        $this->assertSame(CommitHookInstaller::STATUS_INSTALLED, $installer->installPostCommit($this->dir));

        $this->assertStringContainsString('judge --staged', file_get_contents($this->dir . '/.git/hooks/pre-commit'));
        $this->assertStringContainsString('absolve --clear', file_get_contents($this->dir . '/.git/hooks/post-commit'));
    }

    public function test_installs_a_fresh_pre_commit_hook(): void
    {
        $status = (new CommitHookInstaller())->install($this->dir);

        $this->assertSame(CommitHookInstaller::STATUS_INSTALLED, $status);

        $hook = file_get_contents($this->dir . '/.git/hooks/pre-commit');
        $this->assertStringContainsString('judge --staged', $hook);
        $this->assertStringContainsString('Commit blocked', $hook);
        $this->assertTrue(is_executable($this->dir . '/.git/hooks/pre-commit'));
    }

    public function test_is_idempotent_without_force(): void
    {
        $installer = new CommitHookInstaller();
        $installer->install($this->dir);

        $this->assertSame(CommitHookInstaller::STATUS_ALREADY_PRESENT, $installer->install($this->dir));
    }

    public function test_appends_to_an_existing_unrelated_hook(): void
    {
        mkdir($this->dir . '/.git/hooks', 0755, true);
        file_put_contents($this->dir . '/.git/hooks/pre-commit', "#!/usr/bin/env sh\necho hi\n");

        $status = (new CommitHookInstaller())->install($this->dir);

        $this->assertSame(CommitHookInstaller::STATUS_APPENDED, $status);

        $hook = file_get_contents($this->dir . '/.git/hooks/pre-commit');
        $this->assertStringContainsString('echo hi', $hook);
        $this->assertStringContainsString('code-commandments pre-commit gate', $hook);
    }

    public function test_installs_commit_msg_guard_that_blocks_coauthors(): void
    {
        $status = (new CommitHookInstaller())->installCommitMsg($this->dir);
        $this->assertSame(CommitHookInstaller::STATUS_INSTALLED, $status);

        $hook = $this->dir . '/.git/hooks/commit-msg';
        $this->assertStringContainsString('co-authored-by', file_get_contents($hook));

        // A message with a Co-authored-by trailer is rejected.
        file_put_contents($this->dir . '/coauthor.txt', "feat: x\n\nCo-Authored-By: Bob <b@x>\n");
        $this->assertSame(1, $this->runHook($hook, $this->dir . '/coauthor.txt'), 'Co-authored-by message should be blocked');

        // A clean message passes.
        file_put_contents($this->dir . '/clean.txt', "feat: x\n");
        $this->assertSame(0, $this->runHook($hook, $this->dir . '/clean.txt'), 'Clean message should pass');

        @unlink($this->dir . '/coauthor.txt');
        @unlink($this->dir . '/clean.txt');
    }

    public function test_force_refresh_preserves_commit_msg_argument(): void
    {
        $installer = new CommitHookInstaller();
        $installer->installCommitMsg($this->dir);

        // Force-refresh an already-installed guard. The replacement path must
        // not interpret the block's "$1" as a regex backreference (which would
        // strip it, leaving `grep ... ""` that greps an empty filename and
        // never blocks).
        $status = $installer->installCommitMsg($this->dir, force: true);
        $this->assertSame(CommitHookInstaller::STATUS_INSTALLED, $status);

        $hook = $this->dir . '/.git/hooks/commit-msg';
        $this->assertStringContainsString('"$1"', file_get_contents($hook));

        // It still blocks a Co-authored-by trailer after the refresh.
        file_put_contents($this->dir . '/coauthor.txt', "feat: x\n\nCo-Authored-By: Bob <b@x>\n");
        $this->assertSame(1, $this->runHook($hook, $this->dir . '/coauthor.txt'), 'Refreshed guard should still block Co-authored-by');

        @unlink($this->dir . '/coauthor.txt');
    }

    public function test_reports_when_not_a_git_repo(): void
    {
        @rmdir($this->dir . '/.git');

        $status = (new CommitHookInstaller())->install($this->dir);

        $this->assertSame(CommitHookInstaller::STATUS_NOT_GIT, $status);
    }
}
