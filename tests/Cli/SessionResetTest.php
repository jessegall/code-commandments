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
use JesseGall\CodeCommandments\Workspace;
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
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');
        PlanConstraints::inSession(Workspace::at($this->root), $plan)->addLocal('No frontend logic.');
        PlanTesting::inSession(Workspace::at($this->root), $plan)->set('Tests each phase.');
        file_put_contents(Workspace::at($this->root)->path('.plan-working-state'), "## Doing\nphase 2\n");
        Counter::named(Workspace::at($this->root), 'cardinal-remind')->bump();
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

        $this->assertFalse(PlanMarker::inSession(Workspace::at($this->root))->isActive(), 'the plan marker is cleared');
        $this->assertSame([], PlanConstraints::inSession(Workspace::at($this->root), $this->plan()->build())->local());
        $this->assertSame('', PlanTesting::inSession(Workspace::at($this->root), $this->plan()->build())->chosen());
        $this->assertFalse(PlanWorkingState::inSession(Workspace::at($this->root))->exists(), 'the working-state record is wiped');
        $this->assertFileDoesNotExist(Workspace::at($this->root)->path('.cardinal-remind-count'), 'the reminder counter is wiped');
    }

    public function test_clear_also_wipes(): void
    {
        $this->arm();

        $this->fire('clear');

        $this->assertFalse(PlanMarker::inSession(Workspace::at($this->root))->isActive());
    }

    public function test_compaction_leaves_the_active_plan_intact(): void
    {
        $this->arm();

        $this->fire('compact');

        $this->assertTrue(PlanMarker::inSession(Workspace::at($this->root))->isActive(), 'a compaction must not drop a live plan');
        $this->assertSame(['No frontend logic.'], PlanConstraints::inSession(Workspace::at($this->root), $this->plan()->build())->local());
        $this->assertSame('Tests each phase.', PlanTesting::inSession(Workspace::at($this->root), $this->plan()->build())->chosen());
        $this->assertTrue(PlanWorkingState::inSession(Workspace::at($this->root))->exists(), 'the working-state record must survive compaction — it exists to be re-read then');
    }

    public function test_resume_leaves_the_active_plan_intact(): void
    {
        $this->arm();

        $this->fire('resume');

        $this->assertTrue(PlanMarker::inSession(Workspace::at($this->root))->isActive(), 'resuming continues a live session');
    }

    public function test_the_wipe_is_scoped_to_the_payload_session_only(): void
    {
        $mine = Workspace::at($this->root, 'session-abc');
        $concurrent = Workspace::at($this->root, 'session-xyz');
        PlanMarker::inSession($mine)->activate('sha');
        PlanMarker::inSession($concurrent)->activate('sha');
        Counter::named($concurrent, 'cardinal-remind')->bump();

        $io = new CapturingHookIO(new FakeGit($this->root), ['hook_event_name' => 'SessionStart', 'source' => 'startup', 'session_id' => 'session-abc']);
        new SessionReset($io)->run([]);

        $this->assertFalse(PlanMarker::inSession($mine)->isActive(), "the firing session's plan state is wiped");
        $this->assertTrue(PlanMarker::inSession($concurrent)->isActive(), "a concurrent session's plan is untouched");
        $this->assertFileExists($concurrent->path('.cardinal-remind-count'), "a concurrent session's counters are untouched");
    }

    public function test_a_fresh_startup_prunes_stale_session_folders(): void
    {
        $stale = Workspace::at($this->root, 'old-session');
        mkdir($stale->sessionDir(), 0777, true);
        touch($stale->sessionDir(), time() - 30 * 86400);

        $fresh = Workspace::at($this->root, 'fresh-session');
        mkdir($fresh->sessionDir(), 0777, true);

        $this->fire('startup');

        $this->assertDirectoryDoesNotExist($stale->sessionDir(), 'a long-abandoned session folder is swept');
        $this->assertDirectoryExists($fresh->sessionDir(), 'a recently-active sibling survives');
    }

    public function test_a_continuing_session_does_not_prune(): void
    {
        $stale = Workspace::at($this->root, 'old-session');
        mkdir($stale->sessionDir(), 0777, true);
        touch($stale->sessionDir(), time() - 30 * 86400);

        $this->fire('compact');

        $this->assertDirectoryExists($stale->sessionDir(), 'janitorial work belongs to a fresh session only');
    }
}
