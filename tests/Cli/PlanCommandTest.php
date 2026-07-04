<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Plan\PlanCommand;
use JesseGall\CodeCommandments\Cli\Plan\PlanConstraints;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\PlanExecution;
use PHPUnit\Framework\TestCase;

final class PlanCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-plancmd-' . uniqid('', true);
        @mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/.commandments/.plan-active');
        @rmdir($this->root . '/.commandments');
        @rmdir($this->root);
    }

    public function test_done_clears_an_active_plan(): void
    {
        $marker = PlanMarker::inWorktree($this->root);
        $marker->activate('sha0');

        $this->assertSame(0, $this->exec('done'));
        $this->assertFalse($marker->isActive(), 'the keep-going marker is cleared');
    }

    public function test_done_clears_the_leftover_checklist(): void
    {
        PlanMarker::inWorktree($this->root)->activate('sha0');
        file_put_contents($this->root . '/.commandments/sins.md', "- `a.php:1`  A::m  [X]\n");
        file_put_contents($this->root . '/.commandments/sins-2026-07-04_101112.md', "old\n");

        $this->assertSame(0, $this->exec('done'));

        $this->assertFileDoesNotExist($this->root . '/.commandments/sins.md', 'the worklist is gone');
        $this->assertCount(0, glob($this->root . '/.commandments/sins*.md') ?: [], 'its archives too');
    }

    public function test_done_is_a_no_op_without_an_active_plan(): void
    {
        $this->assertSame(0, $this->exec('done'));
        $this->assertFalse(PlanMarker::inWorktree($this->root)->isActive());
    }

    public function test_done_is_blocked_until_constraints_are_verified(): void
    {
        PlanMarker::inWorktree($this->root)->activate('sha');
        $constraints = PlanConstraints::inWorktree($this->root, new PlanExecution()->build());
        $constraints->addLocal('No frontend logic.');

        // Unverified → refused, marker survives.
        $this->assertSame(2, $this->exec('done'));
        $this->assertTrue(PlanMarker::inWorktree($this->root)->isActive());

        // Verified at the current HEAD ('sha', FakeGit's default) → allowed, marker + constraints cleared.
        $constraints->markVerified('sha');
        $this->assertSame(0, $this->exec('done'));
        $this->assertFalse(PlanMarker::inWorktree($this->root)->isActive());
        $this->assertSame([], PlanConstraints::inWorktree($this->root, new PlanExecution()->build())->local());
    }

    public function test_done_gate_is_stale_when_head_moved_since_verification(): void
    {
        PlanMarker::inWorktree($this->root)->activate('sha');
        $constraints = PlanConstraints::inWorktree($this->root, new PlanExecution()->build());
        $constraints->addLocal('No frontend logic.');
        $constraints->markVerified('sha-old'); // verified, then a later commit moved HEAD to 'sha'

        $this->assertSame(2, $this->exec('done'), 'verification is stale — HEAD moved');
    }

    public function test_status_runs_in_both_states(): void
    {
        $this->assertSame(0, $this->exec('status'));

        PlanMarker::inWorktree($this->root)->activate('sha0');
        $this->assertSame(0, $this->exec('status'));
    }

    public function test_status_is_the_default(): void
    {
        $this->assertSame(0, $this->exec());
    }

    public function test_an_unknown_subcommand_is_a_usage_error(): void
    {
        $this->assertSame(2, $this->exec('bogus'));
    }

    private function exec(string ...$args): int
    {
        $command = new PlanCommand(new CapturingHookIO(new FakeGit($this->root)));

        ob_start();
        $code = $command->run(Input::of('plan', $args));
        ob_get_clean();

        return $code;
    }
}
