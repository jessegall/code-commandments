<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Migration;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * Upgrading a project must not throw away what still has a reader: the judge checklists are moved, and
 * only the hook heartbeats and the files of a feature that no longer exists are dropped.
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

    public function test_the_files_of_the_removed_stop_gate_are_deleted(): void
    {
        // The stop gate is gone — deferred work lives in the journal now — so nothing reads these, and
        // every shape the gate ever wrote (live, paused, claim, counters) goes rather than lingering.
        $this->legacy('.until', '4', '0', '7', "3\tthe suite is green");
        $this->legacy('.until.pause', '0', '0', '2', "1\ttests pass");
        $this->legacy('.until.claim', '1', 'the user must choose');
        $this->legacy('.until-work-count', '5');

        $this->assertSame(['4 stop-gate file(s) removed'], $this->migrate());
        $this->assertCount(0, glob($this->session . '/.until*') ?: [], 'nothing of the gate is left');
    }

    public function test_the_files_of_the_removed_plan_are_deleted(): void
    {
        // Plan execution lives in its own package now, so every file a plan ever wrote — its marker,
        // the stuck signal, constraints and their stamp, the testing choice, the working-state record
        // — goes rather than lingering in a folder nothing reads.
        $this->legacy('.plan-active', 'abc123', '2', '9');
        $this->legacy('.plan-stuck', 'abc123');
        file_put_contents($this->session . '/.plan-constraints', "never touch the schema\n");
        file_put_contents($this->session . '/.constraints-verified', "head123\n");
        file_put_contents($this->session . '/.plan-testing', "write the test first\n");
        file_put_contents($this->session . '/.plan-working-state', "doing: the thing\n");

        $this->assertSame(['6 plan file(s) removed'], $this->migrate());
        $this->assertCount(0, glob($this->session . '/.plan-*') ?: [], 'nothing of the plan is left');
        $this->assertFileDoesNotExist($this->session . '/.constraints-verified');
    }

    public function test_hook_counters_are_dropped_rather_than_converted(): void
    {
        // A heartbeat holds nothing of the user's, and the worst a fresh one costs is a nudge landing a
        // few tool uses later than it would have.
        $this->legacy('.cardinal-remind-count', '17');

        $this->assertSame(['1 hook counter(s) reset'], $this->migrate());
        $this->assertFileDoesNotExist($this->session . '/.cardinal-remind-count');
    }

    public function test_judge_checklists_move_into_their_own_folder_and_are_kept(): void
    {
        file_put_contents($this->session . '/sins.md', "the live worklist\n");
        file_put_contents($this->session . '/sins-2026-08-29_154514.md', "an earlier run\n");
        file_put_contents($this->session . '/sins-2026-08-29_200830.md', "another\n");

        $this->assertSame(['3 judge checklist(s) moved into sins/'], $this->migrate());

        $folder = $this->session . '/' . Workspace::SINS;

        $this->assertSame("the live worklist\n", (string) file_get_contents($folder . '/sins.md'));
        $this->assertSame("an earlier run\n", (string) file_get_contents($folder . '/sins-2026-08-29_154514.md'));
        $this->assertSame("another\n", (string) file_get_contents($folder . '/sins-2026-08-29_200830.md'));
        $this->assertCount(0, glob($this->session . '/sins*.md') ?: [], 'nothing is left at the top level');
    }

    public function test_a_checklist_already_in_the_new_folder_is_not_overwritten_by_the_old_one(): void
    {
        mkdir($this->session . '/' . Workspace::SINS, 0777, true);
        file_put_contents($this->session . '/' . Workspace::SINS . '/sins.md', "written since the upgrade\n");
        file_put_contents($this->session . '/sins.md', "the stale one\n");

        $this->assertSame([], $this->migrate(), 'nothing moved');
        $this->assertSame("written since the upgrade\n", (string) file_get_contents($this->session . '/' . Workspace::SINS . '/sins.md'));
    }

    public function test_it_runs_once_and_leaves_a_converted_project_alone(): void
    {
        $this->legacy('.cardinal-remind-count', '17');
        $this->migrate();

        $this->legacy('.cardinal-remind-count', '3');

        $this->assertSame([], $this->migrate(), 'the project is stamped, so there is nothing to do');
        $this->assertFileExists($this->session . '/.cardinal-remind-count');
    }

    public function test_a_project_with_no_state_at_all_is_simply_stamped(): void
    {
        $this->assertSame([], $this->migrate());
        $this->assertFileExists($this->root . '/.commandments/.state-format');
    }
}
