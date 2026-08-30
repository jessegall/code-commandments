<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Plan;
use PHPUnit\Framework\TestCase;

/**
 * A main plan, and a sidequest nested under whatever was being done when it appeared. The PATH is the
 * breadcrumb, so closing a level means deleting a folder and what is left says where you were.
 */
final class PlanTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-plan-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function plan(): Plan
    {
        return new Plan($this->root);
    }

    /**
     * The bug this was found by, and the reason it is first. `File::write` writes through a temporary
     * file BESIDE its target, so it fails on a folder that is not there — and answers false rather than
     * throwing. The plan reported levels it had never written, which is a tool stating a fact it did not
     * measure.
     */
    public function test_opening_a_plan_creates_the_folder_it_needs(): void
    {
        $this->assertDirectoryDoesNotExist($this->root);
        $this->assertTrue($this->plan()->open('the port'), 'it reports what actually happened');
        $this->assertTrue($this->plan()->exists());
        $this->assertSame('the port', $this->plan()->title([]));
    }

    public function test_a_plan_is_opened_once(): void
    {
        $this->plan()->open('the port');

        $this->assertFalse($this->plan()->open('something else'), 'a second open is refused, not silently ignored');
        $this->assertSame('the port', $this->plan()->title([]));
    }

    public function test_a_sidequest_nests_under_where_you_stand(): void
    {
        $plan = $this->plan();
        $plan->open('the port');
        $plan->add([], 'run-failure', 'a walker found it');
        $plan->add(['run-failure'], 'the-enum', 'the path underneath it');

        $this->assertSame([[], ['run-failure'], ['run-failure', 'the-enum']], $plan->levels());
    }

    public function test_a_sidequest_that_already_exists_is_refused(): void
    {
        $plan = $this->plan();
        $plan->open('the port');
        $plan->add([], 'run-failure', 'first');

        $this->assertFalse($plan->add([], 'run-failure', 'again'));
    }

    /**
     * Closing removes ONE level — the deepest — and never the parent it belongs to. Getting this wrong
     * would delete the work a detour came from.
     */
    public function test_closing_removes_only_that_level(): void
    {
        $plan = $this->plan();
        $plan->open('the port');
        $plan->add([], 'run-failure', 'a walker found it');
        $plan->add(['run-failure'], 'the-enum', 'the path underneath it');

        $this->assertTrue($plan->close(['run-failure', 'the-enum'], 'the enum was never the cause'));

        $this->assertSame([[], ['run-failure']], $plan->levels(), 'the parent survives');
        $this->assertTrue($plan->has(['run-failure']));
    }

    /**
     * The REASON goes up into the parent. A conclusion can be re-derived; a reason is what lets a later
     * reader see whether the premise still holds — so it is the half worth keeping when the folder goes.
     */
    public function test_the_reason_goes_up_into_the_parent(): void
    {
        $plan = $this->plan();
        $plan->open('the port');
        $plan->add([], 'run-failure', 'a walker found it');
        $plan->add(['run-failure'], 'the-enum', 'the path underneath it');
        $plan->close(['run-failure', 'the-enum'], 'the enum was never the cause');

        $body = (string) file_get_contents($this->root . '/sidequest/run-failure/README.md');

        $this->assertStringContainsString('the enum was never the cause', $body);
        $this->assertStringContainsString('the-enum', $body, 'and says which level it came from');
    }

    public function test_the_plan_itself_cannot_be_closed(): void
    {
        $this->plan()->open('the port');

        $this->assertFalse($this->plan()->close([], 'done'), 'there is nothing to surface to');
        $this->assertTrue($this->plan()->exists());
    }

    public function test_closing_a_level_that_is_not_there_says_so(): void
    {
        $this->plan()->open('the port');

        $this->assertFalse($this->plan()->close(['never-existed'], 'a reason'));
    }

    /**
     * A parent is only as stale as its most recent child, or a branch with live work in it would look
     * abandoned because its own body has not been edited.
     */
    public function test_a_parent_is_as_fresh_as_its_newest_child(): void
    {
        $plan = $this->plan();
        $plan->open('the port');
        $plan->add([], 'run-failure', 'a walker found it');

        touch($this->root . '/README.md', time() - 7200);

        $this->assertGreaterThan(time() - 60, $plan->touched([])->unwrap(), 'the child keeps the parent fresh');
    }

    public function test_a_level_that_is_not_there_has_no_timestamp(): void
    {
        $this->plan()->open('the port');

        $this->assertTrue($this->plan()->touched(['nothing'])->isNone());
    }

    /**
     * A folder with no body is not a level. Half-written state must not read as work in flight.
     */
    public function test_a_folder_without_a_body_is_not_a_level(): void
    {
        $plan = $this->plan();
        $plan->open('the port');
        mkdir($this->root . '/sidequest/half-made', 0777, true);

        $this->assertSame([[]], $plan->levels());
        $this->assertFalse($plan->has(['half-made']));
    }

    public function test_a_level_falls_back_to_its_folder_name(): void
    {
        $plan = $this->plan();
        $plan->open('the port');
        $plan->add([], 'run-failure', 'no heading of its own');
        file_put_contents($this->root . '/sidequest/run-failure/README.md', 'a body with no heading');

        $this->assertSame('run-failure', $plan->title(['run-failure']));
    }
}
