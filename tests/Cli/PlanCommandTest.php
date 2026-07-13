<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Plan\PlanCommand;
use JesseGall\CodeCommandments\Cli\Plan\PlanConstraints;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\PlanExecution;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

final class PlanCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-plancmd-' . uniqid('', true);
        @mkdir($this->root . '/.commandments', 0777, true);
        @mkdir(Workspace::at($this->root)->sessionDir(), 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink(Workspace::at($this->root)->path('.plan-active'));
        @unlink(Workspace::at($this->root)->path('.plan-stuck'));
        @rmdir($this->root . '/.commandments');
        @rmdir($this->root);
    }

    public function test_stuck_marks_an_active_plan_without_ending_it(): void
    {
        $marker = PlanMarker::inSession(Workspace::at($this->root));
        $marker->activate('sha0');

        $this->assertSame(0, $this->exec('stuck'));

        $this->assertTrue($marker->isActive(), 'a stuck plan stays active — it is not done');
        $this->assertSame('sha', $marker->stuckAt(), 'stuck at the current HEAD (FakeGit default)');
    }

    public function test_stuck_is_a_no_op_without_an_active_plan(): void
    {
        $this->assertSame(0, $this->exec('stuck'));
        $this->assertNull(PlanMarker::inSession(Workspace::at($this->root))->stuckAt());
    }

    public function test_done_clears_a_lingering_stuck_signal(): void
    {
        $marker = PlanMarker::inSession(Workspace::at($this->root));
        $marker->activate('sha');
        $marker->markStuck('sha');

        $this->assertSame(0, $this->exec('done'));
        $this->assertNull($marker->stuckAt(), 'done drops the stuck signal too');
    }

    public function test_done_clears_an_active_plan(): void
    {
        $marker = PlanMarker::inSession(Workspace::at($this->root));
        $marker->activate('sha0');

        $this->assertSame(0, $this->exec('done'));
        $this->assertFalse($marker->isActive(), 'the keep-going marker is cleared');
    }

    public function test_done_clears_the_leftover_checklist(): void
    {
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha0');
        file_put_contents(Workspace::at($this->root)->path('sins.md'), "- `a.php:1`  A::m  [X]\n");
        file_put_contents(Workspace::at($this->root)->path('sins-2026-07-04_101112.md'), "old\n");

        $this->assertSame(0, $this->exec('done'));

        $this->assertFileDoesNotExist(Workspace::at($this->root)->path('sins.md'), 'the worklist is gone');
        $this->assertCount(0, glob(Workspace::at($this->root)->path('sins*.md')) ?: [], 'its archives too');
    }

    public function test_done_is_a_no_op_without_an_active_plan(): void
    {
        $this->assertSame(0, $this->exec('done'));
        $this->assertFalse(PlanMarker::inSession(Workspace::at($this->root))->isActive());
    }

    public function test_done_is_blocked_until_constraints_are_verified(): void
    {
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');
        $constraints = PlanConstraints::inSession(Workspace::at($this->root), new PlanExecution()->build());
        $constraints->addLocal('No frontend logic.');

        // Unverified → refused, marker survives.
        $this->assertSame(2, $this->exec('done'));
        $this->assertTrue(PlanMarker::inSession(Workspace::at($this->root))->isActive());

        // Verified at the current HEAD ('sha', FakeGit's default) → allowed, marker + constraints cleared.
        $constraints->markVerified('sha');
        $this->assertSame(0, $this->exec('done'));
        $this->assertFalse(PlanMarker::inSession(Workspace::at($this->root))->isActive());
        $this->assertSame([], PlanConstraints::inSession(Workspace::at($this->root), new PlanExecution()->build())->local());
    }

    public function test_done_gate_is_stale_when_head_moved_since_verification(): void
    {
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');
        $constraints = PlanConstraints::inSession(Workspace::at($this->root), new PlanExecution()->build());
        $constraints->addLocal('No frontend logic.');
        $constraints->markVerified('sha-old'); // verified, then a later commit moved HEAD to 'sha'

        $this->assertSame(2, $this->exec('done'), 'verification is stale — HEAD moved');
    }

    public function test_status_runs_in_both_states(): void
    {
        $this->assertSame(0, $this->exec('status'));

        PlanMarker::inSession(Workspace::at($this->root))->activate('sha0');
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
