<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Duty;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\Queue;
use JesseGall\CodeCommandments\Cli\Orchestration\QueueCommand;
use JesseGall\CodeCommandments\Cli\Orchestration\Stage;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * `queue next` — the drain a dispatched agent calls on itself. Everything here is about the STOP signal:
 * an agent's loop reads it, and a loop that has to parse a sentence to learn it is finished is one that
 * eventually misreads it. So an empty queue prints NOTHING, and the board stops showing work that has
 * finished.
 */
final class QueueDrainTest extends TestCase
{
    private const string SESSION = 'queue-drain-test';

    private const string PROFILE = 'demo';

    private const string AGENT = 'reviewer';

    private const string PROCEDURE = 'review';

    private string $root;

    /**
     * @var resource
     */
    private $out;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        // REALPATH: git answers with the resolved path, and on this platform the temp dir is a symlink —
        // so an unresolved root would put the arrangement in a folder the command never reads.
        $this->root = realpath(sys_get_temp_dir()) . '/cc-queue-drain-' . uniqid('', true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=' . self::SESSION);

        $this->out = fopen('php://memory', 'r+');
        $this->writeProfile();

        Instance::inSession($this->workspace())->start(self::PROFILE, '10:00');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_an_empty_queue_prints_NOTHING_AT_ALL(): void
    {
        $this->queue()->take('sha-a');

        $exit = $this->drain(self::AGENT);

        $this->assertSame('', $this->said(), 'nothing is the signal to stop, so it must be nothing — not a sentence saying so');
        $this->assertSame(0, $exit, 'finishing is not a failure');
    }

    public function test_an_empty_queue_settles_the_hold_the_agent_took_on_the_board(): void
    {
        $this->queue()->take('sha-a');
        $this->board()->claim(self::PROCEDURE, self::AGENT, '10:00');

        $this->drain(self::AGENT);

        $this->assertSame(
            Stage::Reported,
            $this->board()->on(self::PROCEDURE)->unwrap()->stage,
            'an item left `working` is the record lying, which is the one thing the board exists to prevent',
        );
    }

    public function test_a_waiting_subject_is_handed_over_and_the_board_still_shows_the_agent_working(): void
    {
        $this->queue()->take('sha-a');
        $this->queue()->take('sha-b');
        $this->board()->claim(self::PROCEDURE, self::AGENT, '10:00');

        $this->drain(self::AGENT);

        $this->assertStringContainsString('sha-b', $this->said(), 'the next subject, by name');
        $this->assertSame('sha-b', $this->queue()->running(), 'and taken in the same act, so nothing starts it twice');
        $this->assertSame(Stage::Working, $this->board()->on(self::PROCEDURE)->unwrap()->stage);
    }

    public function test_a_subject_is_never_taken_and_then_left_unsaid(): void
    {
        // The profile is the only thing that knows how to word a brief, and it can be gone by the time
        // the agent asks — the session stopped orchestrating, the binding was dropped. A path that takes
        // a subject and then prints nothing has dropped a commit nobody will review, and the agent reads
        // the silence as "we are done".
        $this->queue()->take('sha-a');
        $this->queue()->take('sha-b');
        Instance::inSession($this->workspace())->stop();

        $this->drain(self::AGENT);

        $this->assertStringContainsString('sha-b', $this->said(), 'what was taken is always handed over');
    }

    public function test_the_drain_reads_the_queue_where_the_SESSION_keeps_it_not_the_lane_it_runs_in(): void
    {
        // The bug that shipped: every path handed to the shell was absolute and worked, and the one thing
        // that resolves its OWN location was wrong. `queue next` was run inside the lane, so it read the
        // lane's session folder — nothing writes there, so a second commit queued and was never picked up.
        $lane = $this->openLane();

        $this->queue()->take('sha-a');
        $this->queue()->take('sha-b');

        putenv('CLAUDE_PROJECT_DIR=' . $lane);

        new QueueCommand(new RecordingHookIO([], new FakeGit($lane)), new Console($this->out))
            ->run(Input::of('queue', ['next', self::AGENT]));

        $this->assertStringContainsString('sha-b', $this->said(), 'a worktree is its own git toplevel, and the queue is not the worktree\'s');
    }

    public function test_naming_no_agent_is_refused_rather_than_answered_for_a_lane_nobody_named(): void
    {
        $this->assertSame(Console::REFUSED, $this->drain(''), 'a refusal a script chains behind must exit non-zero');
        $this->assertStringContainsString('Name the agent', $this->said());
    }

    public function test_status_says_what_each_triggered_agent_is_reading_and_how_much_is_behind_it(): void
    {
        $this->queue()->take('sha-aaaaaaaa');
        $this->queue()->take('sha-b');

        new QueueCommand(new RecordingHookIO([], new FakeGit($this->root)), new Console($this->out))
            ->run(Input::of('queue', ['status']));

        $this->assertStringContainsString(self::AGENT, $this->said());
        $this->assertStringContainsString('reading sha-aaa', $this->said());
        $this->assertStringContainsString('(1 waiting)', $this->said());
    }

    /**
     * A REAL worktree of a real repository. Nothing else reproduces the bug: `ofSession` and a plain
     * worktree-scoped workspace answer the same folder everywhere except inside a checkout of a checkout,
     * which is the one place the trigger actually ran the drain.
     */
    private function openLane(): string
    {
        $lane = $this->root . '/.lanes/' . self::AGENT;

        exec(sprintf('git init -q %s 2>/dev/null', escapeshellarg($this->root)));
        exec(sprintf('git -C %s -c user.email=t@t -c user.name=t commit -q --allow-empty -m init 2>/dev/null', escapeshellarg($this->root)));
        exec(sprintf('git -C %s worktree add -q -b lane %s 2>/dev/null', escapeshellarg($this->root), escapeshellarg($lane)));

        $this->assertDirectoryExists($lane, 'the lane is a checkout of its own, or this proves nothing');

        return $lane;
    }

    private function drain(string $agent): int
    {
        return new QueueCommand(new RecordingHookIO([], new FakeGit($this->root)), new Console($this->out))
            ->run(Input::of('queue', $agent === '' ? ['next'] : ['next', $agent]));
    }

    private function said(): string
    {
        rewind($this->out);

        return (string) stream_get_contents($this->out);
    }

    private function workspace(): Workspace
    {
        return Workspace::ofSession($this->root, self::SESSION);
    }

    private function queue(): Queue
    {
        return Queue::forAgent($this->workspace(), self::AGENT);
    }

    private function board(): Board
    {
        return Board::inSession($this->workspace());
    }

    private function writeProfile(): void
    {
        $profile = $this->root . '/.commandments/orchestrator/profiles/' . self::PROFILE;

        mkdir($profile . '/procedures', 0777, true);
        file_put_contents($profile . '/profile.md', '# ' . self::PROFILE);
        file_put_contents($profile . '/procedures/' . self::PROCEDURE . '.md', 'Read the diff.');
        file_put_contents($profile . '/settings.json', json_encode([
            'commit' => [new Duty(self::AGENT, self::PROCEDURE)->toDeclared()],
        ], JSON_PRETTY_PRINT));
    }
}
