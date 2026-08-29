<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Hooks\Handlers\SharedBranchGate;
use JesseGall\CodeCommandments\Hooks\HookResponse;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use PHPUnit\Framework\TestCase;

/**
 * A rebase of a branch other worktrees stand on rewrites the commits they are built on — and the
 * duplicates are byte-identical, so nothing looks wrong until a merge. The harder half of this is not
 * catching the command but NOT catching text that merely contains it: this rule refused the very commit
 * that introduced it, because the heredoc writing the rule mentioned the flag.
 */
final class SharedBranchGateTest extends TestCase
{
    private string $repo;

    private string $lane;

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir() . '/cc-branchgate-' . uniqid('', true);
        $this->lane = $this->repo . '-lane';
        mkdir($this->repo, 0777, true);

        exec('cd ' . escapeshellarg($this->repo) . ' && git init -q && git commit -q --allow-empty -m first 2>/dev/null');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->repo) . ' ' . escapeshellarg($this->lane));
    }

    private function addWorktree(): void
    {
        exec('cd ' . escapeshellarg($this->repo) . ' && git worktree add -q -b lane ' . escapeshellarg($this->lane) . ' 2>/dev/null');
    }

    private function refusalFor(string $command): string
    {
        $io = new RecordingHookIO([
            'hook_event_name' => 'PreToolUse',
            'tool_name' => 'Bash',
            'tool_input' => ['command' => $command],
        ], new FakeGit($this->repo));

        new SharedBranchGate($io)->run([]);

        return implode("\n", array_map(fn (HookResponse $r) => $r->blockReason->unwrapOr(''), $io->emitted));
    }

    public function test_it_refuses_a_rebasing_pull_while_a_worktree_stands_on_the_branch(): void
    {
        $this->addWorktree();

        $this->assertStringContainsString('rebases a branch', $this->refusalFor('git pull --rebase'));
        $this->assertStringContainsString('rebases a branch', $this->refusalFor('git pull -r origin main'));
    }

    /**
     * With nobody else standing on it, a rebase harms nobody and is not the tool's business.
     */
    public function test_it_allows_a_rebase_when_no_other_worktree_exists(): void
    {
        $this->assertSame('', $this->refusalFor('git pull --rebase'));
    }

    /**
     * The false positive that shipped for one commit: a heredoc WRITING the rule contains the flag, and
     * refusing somebody for describing a command is how a tool gets uninstalled.
     */
    public function test_it_does_not_refuse_text_that_merely_mentions_the_command(): void
    {
        $this->addWorktree();

        $this->assertSame('', $this->refusalFor('cat > rule.php <<\'PHP\'
$flags = ["pull --rebase"];
PHP'));
        $this->assertSame('', $this->refusalFor('echo "never run git pull --rebase here"'));
        $this->assertSame('', $this->refusalFor("grep -n 'pull --rebase' src/"));
    }

    /**
     * A pull that does not rebase is the recommended way out, so it must never be refused.
     */
    public function test_a_fast_forward_pull_is_fine(): void
    {
        $this->addWorktree();

        $this->assertSame('', $this->refusalFor('git pull --ff-only'));
        $this->assertSame('', $this->refusalFor('git rebase -i HEAD~2'), 'only a rebasing PULL is judged');
    }

    /**
     * The command must itself be a git invocation, not merely contain one somewhere.
     */
    public function test_only_an_actual_invocation_counts(): void
    {
        $this->addWorktree();

        $this->assertStringContainsString('rebases', $this->refusalFor('cd /tmp && git pull --rebase'));
        $this->assertSame('', $this->refusalFor('mygit pull --rebase'));
    }
}
