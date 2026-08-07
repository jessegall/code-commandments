<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Plan\PlanConstraints;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanTesting;
use JesseGall\CodeCommandments\Cli\Migration;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Cli\Until\UntilGate;
use JesseGall\CodeCommandments\PlanExecution;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * Upgrading a project must not throw away what the USER asked for. The old layout put one concern per
 * file — a gate, its paused twin, its claim, its counts — and the new one puts each feature's whole
 * state in a single named file; the conditions of a stop gate and a plan's constraints are carried
 * across, and only the hook heartbeats are dropped.
 */
final class StateMigrationTest extends TestCase
{
    private string $root;

    private string $session;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-migrate-' . uniqid('', true);
        $this->session = $this->root . '/.commandments/sessions/abcde';
        mkdir($this->session, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * Plant an OLD marker: positional value lines above the separator.
     */
    private function legacy(string $file, string ...$lines): void
    {
        file_put_contents($this->session . '/' . $file, implode("\n", [...$lines, '-----', 'old explanation', '']));
    }

    private function migrate(): array
    {
        return new Migration(Workspace::at($this->root, 'a-session'))->run();
    }

    private function gate(): UntilGate
    {
        return new UntilGate(new StateFile($this->session . '/.until', UntilGate::legend()));
    }

    public function test_a_gates_conditions_counts_and_claim_become_one_file(): void
    {
        $this->legacy('.until', '4', '0', '7', "3\tthe suite is green", "7\tthe changelog has an entry");
        $this->legacy('.until.claim', '1', 'the user must choose');
        $this->legacy('.until-todo-drift-count', '12');
        $this->legacy('.until-work-count', '5');

        $this->migrate();

        $gate = $this->gate();
        $this->assertSame([3 => 'the suite is green', 7 => 'the changelog has an entry'], $gate->all());
        $this->assertSame(4, $gate->heldStops(), 'the held-stop count carries over');
        $this->assertSame(1, $gate->claimRound(), 'and so does a half-answered claim');
        $this->assertFileDoesNotExist($this->session . '/.until.claim');
        $this->assertFileDoesNotExist($this->session . '/.until-work-count');
    }

    public function test_a_paused_gate_becomes_a_flag_over_the_same_conditions(): void
    {
        $this->legacy('.until.pause', '0', '0', '2', "1\ttests pass", "2\tthe docs build");

        $this->migrate();

        $this->assertTrue($this->gate()->isPaused());
        $this->assertSame([1 => 'tests pass', 2 => 'the docs build'], $this->gate()->pausedConditions());
        $this->assertSame([], $this->gate()->all(), 'and it still holds nothing');
        $this->assertFileDoesNotExist($this->session . '/.until.pause');
    }

    public function test_a_split_gate_from_an_older_session_keeps_both_sides(): void
    {
        // #403/#418 left some sessions with a live AND a paused marker. The live one wins — but the
        // set-aside conditions are the user's too, so they are folded in rather than dropped.
        $this->legacy('.until', '0', '0', '1', "1\tlive one");
        $this->legacy('.until.pause', '0', '0', '1', "1\tset aside");

        $this->migrate();

        $this->assertSame([1 => 'live one', 2 => 'set aside'], $this->gate()->all());
        $this->assertFalse($this->gate()->isPaused());
    }

    public function test_a_pre_stable_ids_gate_reads_back_with_positional_ids(): void
    {
        $this->legacy('.until', '0', '0', 'tests pass', 'readme updated');

        $this->migrate();

        $this->assertSame([1 => 'tests pass', 2 => 'readme updated'], $this->gate()->all());
    }

    public function test_the_plan_marker_absorbs_its_separate_stuck_signal(): void
    {
        $this->legacy('.plan-active', 'abc123', '2', '9');
        $this->legacy('.plan-stuck', 'abc123');

        $this->migrate();

        $marker = new PlanMarker(new StateFile($this->session . '/.plan-active', PlanMarker::legend()));
        $this->assertTrue($marker->isActive());
        $this->assertSame('abc123', $marker->stuckAt());
        $this->assertFileDoesNotExist($this->session . '/.plan-stuck');
    }

    public function test_the_plan_constraints_absorb_their_verification_stamp(): void
    {
        file_put_contents($this->session . '/.plan-constraints', "never touch the schema\nkeep the API stable\n");
        file_put_contents($this->session . '/.constraints-verified', "head123\n");

        $this->migrate();

        $constraints = new PlanConstraints(
            new StateFile($this->session . '/.plan-constraints', PlanConstraints::legend()),
            new PlanExecution()->build(),
        );

        $this->assertSame(['never touch the schema', 'keep the API stable'], $constraints->local());
        $this->assertTrue($constraints->isVerifiedAt('head123'));
        $this->assertFileDoesNotExist($this->session . '/.constraints-verified');
    }

    public function test_the_testing_methodology_carries_over(): void
    {
        file_put_contents($this->session . '/.plan-testing', "write the test first, then the code\n");

        $this->migrate();

        $testing = new PlanTesting(
            new StateFile($this->session . '/.plan-testing', PlanTesting::legend()),
            new PlanExecution()->build(),
        );

        $this->assertSame('write the test first, then the code', $testing->chosen());
    }

    public function test_hook_counters_are_dropped_rather_than_converted(): void
    {
        // A heartbeat holds nothing of the user's, and the worst a fresh one costs is a nudge landing a
        // few tool uses later than it would have.
        $this->legacy('.cardinal-remind-count', '17');

        $this->assertSame(['1 hook counter(s) reset'], $this->migrate());
        $this->assertFileDoesNotExist($this->session . '/.cardinal-remind-count');
    }

    public function test_it_runs_once_and_leaves_a_converted_project_alone(): void
    {
        $this->legacy('.until', '0', '0', '1', "1\ttests pass");
        $this->migrate();

        $this->gate()->add('a condition set after the upgrade');
        $before = (string) file_get_contents($this->session . '/.until');

        $this->assertSame([], $this->migrate(), 'the project is stamped, so there is nothing to do');
        $this->assertSame($before, (string) file_get_contents($this->session . '/.until'));
    }

    public function test_a_project_with_no_state_at_all_is_simply_stamped(): void
    {
        $this->assertSame([], $this->migrate());
        $this->assertFileExists($this->root . '/.commandments/.state-format');
    }
}
