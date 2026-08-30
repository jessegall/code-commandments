<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Task;
use JesseGall\CodeCommandments\Cli\Orchestration\TaskId;
use JesseGall\CodeCommandments\Cli\Orchestration\Tasks;
use JesseGall\CodeCommandments\Cli\Orchestration\TaskState;
use PHPUnit\Framework\TestCase;

/**
 * A task is one file with a NUMBER, and the folder it sits in is its state. Both halves are load-bearing:
 * the number is what an orchestrator quotes into a brief, and moving the file is the only way the state
 * changes — so there is nothing to keep in step and nothing that can disagree.
 */
final class TaskTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-tasks-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function tasks(): Tasks
    {
        return new Tasks($this->root);
    }

    public function test_the_first_task_is_number_one_and_lands_in_the_queue(): void
    {
        $task = $this->tasks()->add(TaskId::board(), 'Port the widget', 'the main thrust')->unwrap();

        $this->assertSame('001', $task->id->render());
        $this->assertSame(TaskState::Queued, $task->state);
        $this->assertFileExists($this->root . '/queue/001-port-the-widget.md');
    }

    public function test_a_task_keeps_the_title_and_the_why_it_was_given(): void
    {
        $this->tasks()->add(TaskId::board(), 'Port the widget', 'the main thrust');

        $task = $this->tasks()->find(TaskId::of(1))->unwrap();

        $this->assertSame('Port the widget', $task->title);
        $this->assertSame('the main thrust', $task->why);
    }

    public function test_numbers_are_handed_out_in_order(): void
    {
        $tasks = $this->tasks();
        $tasks->add(TaskId::board(), 'first', '');
        $second = $tasks->add(TaskId::board(), 'second', '')->unwrap();

        $this->assertSame('002', $second->id->render());
        $this->assertSame('second', $tasks->find(TaskId::of(2))->unwrap()->title);
    }

    /**
     * The point of the whole design: a subtask carries its parent's NUMBER and nothing else. It sits in
     * the same folder as every other queued task, so nothing has to be moved or nested to say what it
     * belongs to, and `002.1` means the same thing in a listing, a filename and a worker's brief.
     */
    public function test_a_subtask_carries_its_parents_number_and_no_folder(): void
    {
        $tasks = $this->tasks();
        $tasks->add(TaskId::board(), 'first', '');
        $parent = $tasks->add(TaskId::board(), 'Fix the enum', 'a walker found it')->unwrap();

        $child = $tasks->add($parent->id, 'Probe the parser', 'is it even the enum?')->unwrap();

        $this->assertSame('002.1', $child->id->render());
        $this->assertFileExists($this->root . '/queue/002.1-probe-the-parser.md');
        $this->assertTrue($child->id->isChildOf($parent->id));
    }

    public function test_subtasks_number_within_their_own_parent(): void
    {
        $tasks = $this->tasks();
        $first = $tasks->add(TaskId::board(), 'first', '')->unwrap();
        $second = $tasks->add(TaskId::board(), 'second', '')->unwrap();

        $tasks->add($first->id, 'under the first', '');
        $under = $tasks->add($second->id, 'under the second', '')->unwrap();

        $this->assertSame('002.1', $under->id->render(), 'the second parent numbers from its OWN children');
    }

    /**
     * One ordered listing IS the tree — a parent's number sorts immediately above its own subtasks, so
     * nothing has to be grouped or re-nested to show the shape.
     */
    public function test_the_listing_reads_as_the_tree(): void
    {
        $tasks = $this->tasks();
        $first = $tasks->add(TaskId::board(), 'first', '')->unwrap();
        $tasks->add(TaskId::board(), 'second', '');
        $tasks->add($first->id, 'under the first', '');

        $rendered = array_map(static fn (Task $task): string => $task->id->render(), $tasks->all());

        $this->assertSame(['001', '001.1', '002'], $rendered);
    }

    public function test_starting_a_task_moves_the_file_into_active(): void
    {
        $tasks = $this->tasks();
        $task = $tasks->add(TaskId::board(), 'Port the widget', 'the main thrust')->unwrap();

        $started = $tasks->move($task, TaskState::Active, '')->unwrap();

        $this->assertSame(TaskState::Active, $started->state);
        $this->assertFileExists($this->root . '/active/001-port-the-widget.md');
        $this->assertFileDoesNotExist($this->root . '/queue/001-port-the-widget.md');
    }

    /**
     * The REASON is the half worth keeping: a conclusion can be re-derived, where a reason is what lets a
     * later reader see whether the premise still holds. So it goes into the file that survives into
     * `history/`, not into a message that scrolls away.
     */
    public function test_closing_a_task_files_it_in_history_with_what_came_of_it(): void
    {
        $tasks = $this->tasks();
        $task = $tasks->add(TaskId::board(), 'Fix the enum', 'a walker found it')->unwrap();

        $closed = $tasks->move($task, TaskState::Done, 'the enum was never the cause')->unwrap();

        $this->assertSame(TaskState::Done, $closed->state);
        $this->assertFileExists($this->root . '/history/001-fix-the-enum.md');
        $this->assertSame('the enum was never the cause', $closed->outcome()->unwrap());
    }

    /**
     * A state entered WITHOUT a reason has none, and that is not the same as a reason that is empty. The
     * two lived under one value while the reader manufactured `''` for a line carrying no reason at all —
     * so a history could not say whether nobody gave a reason or somebody gave a blank one, which is
     * exactly the question a history is read to answer.
     */
    public function test_a_state_entered_without_a_reason_has_no_outcome(): void
    {
        $tasks = $this->tasks();
        $task = $tasks->add(TaskId::board(), 'Fix the enum', 'a walker found it')->unwrap();

        $started = $tasks->move($task, TaskState::Active, '')->unwrap();

        $this->assertTrue($started->outcome()->isNone(), 'no reason was given, so there is no outcome');
    }

    public function test_the_file_logs_every_state_it_entered(): void
    {
        $tasks = $this->tasks();
        $task = $tasks->add(TaskId::board(), 'Fix the enum', 'a walker found it')->unwrap();
        $started = $tasks->move($task, TaskState::Active, '')->unwrap();
        $closed = $tasks->move($started, TaskState::Done, 'it was the parser')->unwrap();

        $body = $closed->body();

        $this->assertStringContainsString('- queued ', $body);
        $this->assertStringContainsString('- started ', $body);
        $this->assertStringContainsString('- done ', $body);
        $this->assertStringContainsString('it was the parser', $body);
    }

    /**
     * A closed task still answers to its address, which is what makes a number worth quoting long after
     * the work is done — and it is also why a number is never handed out twice.
     */
    public function test_history_keeps_its_number_and_the_next_task_gets_a_fresh_one(): void
    {
        $tasks = $this->tasks();
        $task = $tasks->add(TaskId::board(), 'Fix the enum', '')->unwrap();
        $tasks->move($task, TaskState::Done, 'done with it');

        $next = $tasks->add(TaskId::board(), 'the next thing', '')->unwrap();

        $this->assertSame('002', $next->id->render());
        $this->assertTrue($tasks->find(TaskId::of(1))->isSome(), 'the closed one is still addressable');
    }

    public function test_the_board_leaves_out_what_is_closed(): void
    {
        $tasks = $this->tasks();
        $done = $tasks->add(TaskId::board(), 'already done', '')->unwrap();
        $tasks->add(TaskId::board(), 'still owed', '');
        $tasks->move($done, TaskState::Done, 'finished');

        $live = $tasks->inState(...TaskState::live());

        $this->assertCount(1, $live);
        $this->assertSame('still owed', $live[0]->title);
    }

    public function test_a_task_nobody_wrote_is_not_found(): void
    {
        $this->assertTrue($this->tasks()->find(TaskId::of(7))->isNone());
    }

    /**
     * A stray file in one of the three folders is not a task. Reading it as one would give it a number
     * nothing assigned, which the next `add` would then hand out again.
     */
    public function test_a_file_with_no_address_is_not_a_task(): void
    {
        mkdir($this->root . '/queue', 0777, true);
        file_put_contents($this->root . '/queue/notes.md', '# a stray note');

        $this->assertSame([], $this->tasks()->all());
    }

    public function test_an_address_is_read_back_from_how_it_is_written(): void
    {
        $this->assertSame('002.1', TaskId::parse('002.1')->unwrap()->render());
        $this->assertSame('002', TaskId::parse('2')->unwrap()->render(), 'the padding is written, not typed');
        $this->assertTrue(TaskId::parse('')->isNone());
        $this->assertTrue(TaskId::parse('two')->isNone());
        $this->assertTrue(TaskId::parse('0')->isNone(), 'nothing is ever numbered zero');
    }

    public function test_the_board_is_what_every_top_level_task_is_a_child_of(): void
    {
        $this->assertTrue(TaskId::of(2)->isChildOf(TaskId::board()));
        $this->assertTrue(TaskId::of(2, 1)->isChildOf(TaskId::of(2)));
        $this->assertFalse(TaskId::of(2, 1)->isChildOf(TaskId::board()), 'a subtask is not top level');
    }
}
