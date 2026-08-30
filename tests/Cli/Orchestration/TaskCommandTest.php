<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\OrchestrateCommand;
use JesseGall\CodeCommandments\Cli\Orchestration\TaskCommand;
use JesseGall\CodeCommandments\Cli\Orchestration\TaskId;
use JesseGall\CodeCommandments\Cli\Orchestration\Tasks;
use JesseGall\CodeCommandments\Cli\Orchestration\TaskState;
use JesseGall\CodeCommandments\Tests\Cli\CapturingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The verbs, checked through what they LEAVE BEHIND. A command that printed a plausible line and moved no
 * file would pass an assertion about its output, so every move is verified against the folder the file is
 * actually filed under — the folder IS the state, and that is the only thing worth measuring.
 */
final class TaskCommandTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    /**
     * @var resource  where the last run's words went, so an assertion reads what the user would see
     */
    private $out;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-task-cli-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=task-test');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function exec(string ...$argv): int
    {
        $this->out = fopen('php://memory', 'r+');

        return new TaskCommand(new CapturingHookIO(new FakeGit($this->root)), new Console($this->out))
            ->run(Input::fromArgv(['commandments', 'task', ...$argv]));
    }

    private function said(): string
    {
        rewind($this->out);

        return (string) stream_get_contents($this->out);
    }

    private function tasks(): Tasks
    {
        return Tasks::inSession(Workspace::ofSession($this->root));
    }

    public function test_add_prints_the_number_it_assigned(): void
    {
        $this->assertSame(0, $this->exec('add', 'Port the widget', 'the main thrust'));

        $this->assertStringContainsString('001', $this->said());
        $this->assertSame(TaskState::Queued, $this->tasks()->find(TaskId::of(1))->unwrap()->state);
    }

    public function test_a_task_needs_a_title(): void
    {
        ob_start();
        $exit = $this->exec('add');
        ob_end_clean();

        $this->assertSame(2, $exit, 'a malformed invocation is a usage error, not a refusal');
        $this->assertSame([], $this->tasks()->all(), 'and nothing was written');
    }

    /**
     * A why is optional — a task queued mid-flight has to cost one breath, or it does not get queued at
     * all. What is NOT optional is the reason it closes on.
     */
    public function test_a_title_alone_is_enough_to_queue_one(): void
    {
        $this->assertSame(0, $this->exec('add', 'Port the widget'));

        $task = $this->tasks()->find(TaskId::of(1))->unwrap();

        $this->assertSame('Port the widget', $task->title);
        $this->assertSame('', $task->why);
    }

    public function test_a_subtask_is_added_under_a_number(): void
    {
        $this->exec('add', 'Fix the enum', 'a walker found it');

        $this->assertSame(0, $this->exec('add', '--under=001', 'Probe the parser', 'is it even the enum?'));

        $this->assertTrue($this->tasks()->find(TaskId::of(1, 1))->isSome());
        $this->assertStringContainsString('001.1', $this->said());
    }

    /**
     * A `--under` nobody can resolve must not quietly become a top-level task. The number IS the
     * parentage, so getting it wrong silently is the one failure this design cannot survive.
     */
    public function test_a_subtask_of_nothing_is_refused(): void
    {
        $this->assertSame(Console::REFUSED, $this->exec('add', '--under=009', 'Probe the parser', 'why'));
        $this->assertSame([], $this->tasks()->all(), 'nothing was created');
    }

    public function test_start_moves_the_file_into_active(): void
    {
        $this->exec('add', 'Port the widget', 'the main thrust');

        $this->assertSame(0, $this->exec('start', '001'));

        $this->assertSame(TaskState::Active, $this->tasks()->find(TaskId::of(1))->unwrap()->state);
    }

    public function test_starting_what_is_already_active_is_refused(): void
    {
        $this->exec('add', 'Port the widget', '');
        $this->exec('start', '001');

        $this->assertSame(Console::REFUSED, $this->exec('start', '001'));
    }

    public function test_done_files_it_in_history_with_the_reason(): void
    {
        $this->exec('add', 'Fix the enum', 'a walker found it');
        $this->exec('start', '001');

        $this->assertSame(0, $this->exec('done', '001', 'the enum was never the cause'));

        $closed = $this->tasks()->find(TaskId::of(1))->unwrap();

        $this->assertSame(TaskState::Done, $closed->state);
        $this->assertSame('the enum was never the cause', $closed->outcome());
    }

    /**
     * The reason is the half worth keeping, so closing without one is refused rather than recorded as a
     * blank — a history of tasks that all say nothing is the same as no history.
     */
    public function test_closing_without_a_reason_is_refused_and_moves_nothing(): void
    {
        $this->exec('add', 'Fix the enum', '');

        ob_start();
        $exit = $this->exec('done', '001');
        ob_end_clean();

        $this->assertSame(2, $exit);
        $this->assertSame(TaskState::Queued, $this->tasks()->find(TaskId::of(1))->unwrap()->state);
    }

    public function test_a_number_nobody_has_is_refused(): void
    {
        $this->assertSame(Console::REFUSED, $this->exec('start', '009'));
        $this->assertSame(Console::REFUSED, $this->exec('done', '009', 'a reason'));
        $this->assertSame(Console::REFUSED, $this->exec('show', 'nonsense'));
    }

    public function test_the_board_shows_what_is_owed_and_leaves_out_what_is_closed(): void
    {
        $this->exec('add', 'Port the widget', 'the main thrust');
        $this->exec('add', 'Fix the enum', '');
        $this->exec('done', '002', 'it was the parser');

        $this->assertSame(0, $this->exec());

        $said = $this->said();

        $this->assertStringContainsString('001', $said);
        $this->assertStringContainsString('Port the widget', $said);
        $this->assertStringNotContainsString('Fix the enum', $said, 'a closed task is history, not board');
    }

    public function test_history_says_what_came_of_each(): void
    {
        $this->exec('add', 'Fix the enum', '');
        $this->exec('done', '001', 'it was the parser');

        $this->assertSame(0, $this->exec('history'));

        $said = $this->said();

        $this->assertStringContainsString('001', $said);
        $this->assertStringContainsString('it was the parser', $said);
    }

    public function test_show_reads_the_whole_file_out(): void
    {
        $this->exec('add', 'Port the widget', 'the main thrust');

        $this->assertSame(0, $this->exec('show', '001'));

        $said = $this->said();

        $this->assertStringContainsString('# Port the widget', $said);
        $this->assertStringContainsString('the main thrust', $said);
        $this->assertStringContainsString('- queued ', $said);
    }

    public function test_stale_names_active_work_nobody_has_touched(): void
    {
        $this->exec('add', 'Port the widget', '');
        $this->exec('start', '001');

        $task = $this->tasks()->find(TaskId::of(1))->unwrap();
        touch($task->path, time() - 7200);

        $this->exec('stale', '--for=60');

        $this->assertStringContainsString('001', $this->said());
    }

    public function test_an_unknown_verb_is_a_usage_error_rather_than_the_board(): void
    {
        ob_start();
        $exit = $this->exec('bogus');
        ob_end_clean();

        $this->assertSame(2, $exit);
    }

    /**
     * `orchestrate plan` used to answer, so a caller who keeps typing it has to be TOLD it is gone —
     * otherwise the command apparently succeeds and records nothing, which is exactly how a version-skewed
     * caller loses a morning.
     */
    public function test_the_old_plan_verb_says_where_the_work_went(): void
    {
        $out = fopen('php://memory', 'r+');
        $exit = new OrchestrateCommand(new CapturingHookIO(new FakeGit($this->root)), new Console($out))
            ->run(Input::fromArgv(['commandments', 'orchestrate', 'plan', 'open', 'the port']));

        rewind($out);

        $this->assertSame(Console::REFUSED, $exit);
        $this->assertStringContainsString('commandments task', (string) stream_get_contents($out));
    }
}
