<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\Handlers\SessionReset;
use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanConstraints;
use JesseGall\CodeCommandments\Cli\Plan\PlanTesting;
use JesseGall\CodeCommandments\Cli\Plan\PlanWorkingState;
use JesseGall\CodeCommandments\PlanExecution;
use PHPUnit\Framework\TestCase;

/**
 * The fresh-session cleanup: it wipes the worktree's plan marker, constraints, testing choice, and
 * reminder counters on a genuinely-new session (`startup`/`clear`) — but leaves them intact when a
 * session merely continues (`resume`/`compact`), since compaction re-fires SessionStart mid-plan.
 */
final class SessionResetTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-session-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function arm(): void
    {
        $plan = new PlanExecution()->build();
        PlanMarker::inWorktree($this->root)->activate('sha');
        PlanConstraints::inWorktree($this->root, $plan)->addLocal('No frontend logic.');
        PlanTesting::inWorktree($this->root, $plan)->set('Tests each phase.');
        file_put_contents($this->root . '/.commandments/.plan-working-state', "## Doing\nphase 2\n");
        Counter::named($this->root, 'cardinal-remind')->bump();
    }

    private function fire(string $source): void
    {
        $io = new CapturingHookIO(new FakeGit($this->root), ['hook_event_name' => 'SessionStart', 'source' => $source]);
        new SessionReset($io)->run([]);
    }

    private function plan(): PlanExecution
    {
        return new PlanExecution;
    }

    public function test_a_fresh_startup_wipes_all_lingering_plan_state(): void
    {
        $this->arm();

        $this->fire('startup');

        $this->assertFalse(PlanMarker::inWorktree($this->root)->isActive(), 'the plan marker is cleared');
        $this->assertSame([], PlanConstraints::inWorktree($this->root, $this->plan()->build())->local());
        $this->assertSame('', PlanTesting::inWorktree($this->root, $this->plan()->build())->chosen());
        $this->assertFalse(PlanWorkingState::inWorktree($this->root)->exists(), 'the working-state record is wiped');
        $this->assertFileDoesNotExist($this->root . '/.commandments/.cardinal-remind-count', 'the reminder counter is wiped');
    }

    public function test_clear_also_wipes(): void
    {
        $this->arm();

        $this->fire('clear');

        $this->assertFalse(PlanMarker::inWorktree($this->root)->isActive());
    }

    public function test_compaction_leaves_the_active_plan_intact(): void
    {
        $this->arm();

        $this->fire('compact');

        $this->assertTrue(PlanMarker::inWorktree($this->root)->isActive(), 'a compaction must not drop a live plan');
        $this->assertSame(['No frontend logic.'], PlanConstraints::inWorktree($this->root, $this->plan()->build())->local());
        $this->assertSame('Tests each phase.', PlanTesting::inWorktree($this->root, $this->plan()->build())->chosen());
        $this->assertTrue(PlanWorkingState::inWorktree($this->root)->exists(), 'the working-state record must survive compaction — it exists to be re-read then');
    }

    public function test_resume_leaves_the_active_plan_intact(): void
    {
        $this->arm();

        $this->fire('resume');

        $this->assertTrue(PlanMarker::inWorktree($this->root)->isActive(), 'resuming continues a live session');
    }
}
