<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Duty;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\Queue;
use JesseGall\CodeCommandments\Hooks\HeadMark;
use JesseGall\CodeCommandments\Hooks\Handlers\CommitTrigger;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The commit trigger, which produced four bugs in two hours and was found by a person watching it run
 * every time. Each of the four is a case here, because the one act it performs — starting an agent —
 * used to be an `exec()` nothing could read: a dispatch that cannot be inspected is a dispatch nothing
 * can check, so the shell command is now handed to a collaborator a test replaces.
 */
final class CommitTriggerTest extends TestCase
{
    /**
     * The session both the payload and the environment name, so what the hook writes and what the test
     * reads back land in ONE folder.
     */
    private const string SESSION = 'commit-trigger-test';

    private const string PROFILE = 'demo';

    private const string AGENT = 'reviewer';

    private const string PROCEDURE = 'review';

    private string $root;

    private FakeGit $git;

    private FakeDetached $detached;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-commit-trigger-' . uniqid('', true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=' . self::SESSION);

        $this->git = new FakeGit($this->root, head: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $this->detached = new FakeDetached;

        $this->writeProfile();

        // AFTER the environment is set: `ofSession` reads it, so starting first would file the instance
        // under whichever session the suite itself is running in.
        Instance::inSession($this->workspace())->start(self::PROFILE, '10:00');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_commit_that_did_not_move_this_checkouts_head_dispatches_nothing(): void
    {
        // A builder checkpointing in its own lane raises this moment in the session that spawned it, and
        // the sha it made is not one of ours. Three lanes checkpointing per widget is fifteen reviews of
        // drafts for work that lands as three merges.
        $this->alreadySeen($this->git->head);

        $said = $this->commit();

        $this->assertSame([], $this->detached->started, 'nothing may be started for a commit that landed elsewhere');
        $this->assertSame('', $said, 'and nothing may be said about it either');
    }

    public function test_the_same_commit_seen_twice_is_answered_once(): void
    {
        $this->commit();
        $this->commit();

        $this->assertCount(1, $this->detached->started, 'one commit, one dispatch, however often the moment fires');
    }

    public function test_a_commit_that_moved_this_checkouts_head_is_dispatched(): void
    {
        $said = $this->commit();

        $this->assertCount(1, $this->detached->started);
        $this->assertStringContainsString(self::AGENT, $said);
        $this->assertSame(
            self::AGENT,
            $this->board()->on(self::PROCEDURE)->unwrap()->hold->holder,
            'the agent holds its procedure on the board, where the orchestrator already looks',
        );
    }

    public function test_the_drain_runs_in_the_checkout_that_holds_the_queue_not_in_the_lane(): void
    {
        $this->commit();

        // `queue next` resolves its OWN workspace from the project it runs in — the one thing handed to
        // the shell that is not an absolute path. Run inside the lane it reads a session folder nothing
        // writes to, so a second commit queues and is then never picked up.
        $this->assertStringContainsString(
            sprintf('(cd %s && %s queue next', escapeshellarg($this->root), escapeshellarg('vendor/bin/commandments')),
            $this->onlyLaunch()->command,
            'the drain runs where the queue lives',
        );
    }

    public function test_the_agent_itself_runs_in_its_own_lane(): void
    {
        $this->commit();

        $this->assertStringContainsString(
            'cd ' . escapeshellarg($this->root . '/.lanes/' . self::AGENT),
            $this->onlyLaunch()->command,
            'the reading happens in a checkout of its own',
        );
    }

    public function test_the_conversation_id_is_minted_once_and_every_later_dispatch_resumes_it(): void
    {
        $this->commit();

        $conversation = $this->queue()->conversation();

        $this->assertNotSame('', $conversation, 'the agent is given a conversation of its own');
        $this->assertStringContainsString('--session-id ' . escapeshellarg($conversation), $this->onlyLaunch()->command);
        $this->assertStringNotContainsString('--continue', $this->onlyLaunch()->command, '`--continue` resolves to the most recent conversation in a directory, so every spawn forks off one root');

        // The agent finishes, so the lane is free — and the next commit must reach the SAME reader.
        $this->queue()->finishAndTakeNext();
        $this->git->head = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $this->commit();

        $this->assertCount(2, $this->detached->started);
        $this->assertSame($conversation, $this->queue()->conversation(), 'minted ONCE — that is the whole property');
        $this->assertStringContainsString('--resume ' . escapeshellarg($conversation), $this->detached->started[1]->command);
        $this->assertStringNotContainsString('--session-id', $this->detached->started[1]->command, 'a session id is claimed once; asking for it twice is a second conversation');
    }

    public function test_the_loop_stops_on_an_EMPTY_brief_rather_than_on_words_it_has_to_read(): void
    {
        $this->commit();

        $prompt = escapeshellarg($this->workspace()->path('.' . self::AGENT . '.prompt'));

        // `queue next` prints the next brief or prints nothing, and NOTHING is the signal to stop: a loop
        // that must parse a sentence to learn it is finished is one that eventually misreads it.
        $this->assertStringContainsString("[ -s {$prompt} ] || break", $this->onlyLaunch()->command);
    }

    public function test_the_loop_reassigns_the_flag_so_every_later_pass_resumes_one_conversation(): void
    {
        $this->commit();

        // One reader, in order, rather than five that each know only their own diff.
        $this->assertStringContainsString(
            'c=--resume ' . escapeshellarg($this->queue()->conversation()),
            $this->onlyLaunch()->command,
            'the pass after the first resumes rather than starts',
        );
    }

    public function test_a_procedure_the_profile_never_wrote_is_said_and_nothing_is_dispatched(): void
    {
        $this->bind(new Duty(self::AGENT, 'unwritten'));

        $said = $this->commit();

        $this->assertSame([], $this->detached->started, 'an agent cannot be sent to do what nobody wrote down');
        $this->assertStringContainsString('has not written it', $said, 'and the gap is named rather than passed over in silence');
    }

    public function test_a_second_commit_while_the_agent_is_reading_is_queued_rather_than_started(): void
    {
        $this->commit();

        $this->git->head = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $said = $this->commit();

        $this->assertCount(1, $this->detached->started, 'an agent is a conversation, not a pool');
        $this->assertSame(['bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb'], $this->queue()->waiting(), 'dropping it would be worse — a commit nobody reviewed is invisible');
        $this->assertStringContainsString('QUEUED', $said);
    }

    public function test_two_agents_on_one_trigger_do_not_write_over_each_others_log_or_prompt(): void
    {
        $this->bind(new Duty(self::AGENT, self::PROCEDURE), new Duty('auditor', self::PROCEDURE));

        $this->commit();

        $launches = $this->detached->started;

        $this->assertCount(2, $launches);
        $this->assertNotSame($launches[0]->log, $launches[1]->log, 'two reviews in one file is two reviews nobody can tell apart');
        $this->assertStringContainsString(self::AGENT, $launches[0]->log);
        $this->assertStringContainsString('auditor', $launches[1]->log);
        $this->assertStringContainsString(
            escapeshellarg($this->workspace()->path('.auditor.prompt')),
            $launches[1]->command,
            'and each is handed its own brief',
        );
    }

    public function test_a_tool_call_that_is_not_a_commit_is_passed_over(): void
    {
        $said = $this->fire(['tool_name' => 'Bash', 'tool_input' => ['command' => 'git status']]);

        $this->assertSame([], $this->detached->started);
        $this->assertSame('', $said);
    }

    /**
     * Fire the moment a `git commit` raises, and answer with everything the hook said.
     */
    private function commit(): string
    {
        return $this->fire(['tool_name' => 'Bash', 'tool_input' => ['command' => 'git commit -m "a change"']]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fire(array $payload): string
    {
        $io = new RecordingHookIO(
            [...$payload, 'hook_event_name' => 'PostToolUse', 'session_id' => self::SESSION],
            $this->git,
        );

        new CommitTrigger($io, $this->detached)->run([]);

        $said = [];

        foreach ($io->emitted as $response) {
            $said[] = $response->context->unwrapOr('');
        }

        return implode("\n", $said);
    }

    /**
     * Pretend this checkout has already answered for $head — what a lane's own checkpoint leaves behind.
     */
    private function alreadySeen(string $head): void
    {
        HeadMark::named($this->workspace(), 'commit-trigger')->movedTo($head);
    }

    private function onlyLaunch(): Launch
    {
        $this->assertCount(1, $this->detached->started, 'exactly one dispatch to read');

        return $this->detached->started[0];
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

    /**
     * A profile with one agent bound to one written procedure — the shape every case starts from.
     */
    private function writeProfile(): void
    {
        $profile = $this->root . '/.commandments/orchestrator/profiles/' . self::PROFILE;

        mkdir($profile . '/procedures', 0777, true);
        mkdir($profile . '/roles', 0777, true);
        file_put_contents($profile . '/profile.md', '# ' . self::PROFILE);
        file_put_contents($profile . '/procedures/' . self::PROCEDURE . '.md', 'Read the diff. Say what is unidiomatic.');
        file_put_contents($profile . '/roles/' . self::AGENT . '.md', 'You read, you do not write.');
        file_put_contents($profile . '/roles/auditor.md', 'You read, you do not write.');

        $this->bind(new Duty(self::AGENT, self::PROCEDURE));
    }

    /**
     * Rewrite the profile's `commit` bindings to exactly what is named — the switch a case flips.
     */
    private function bind(Duty ...$duties): void
    {
        $profile = $this->root . '/.commandments/orchestrator/profiles/' . self::PROFILE;
        $bound = [];

        foreach ($duties as $duty) {
            $bound[] = $duty->toDeclared();
        }

        file_put_contents($profile . '/settings.json', json_encode(['commit' => $bound], JSON_PRETTY_PRINT));
    }
}
