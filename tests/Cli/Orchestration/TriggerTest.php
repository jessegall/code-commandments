<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Dispatched;
use JesseGall\CodeCommandments\Cli\Orchestration\Dispatcher;
use JesseGall\CodeCommandments\Cli\Orchestration\Duty;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\Pending;
use JesseGall\CodeCommandments\Hooks\Handlers\CommitTrigger;
use JesseGall\CodeCommandments\Hooks\Handlers\WorkerFinishedTrigger;
use JesseGall\CodeCommandments\Hooks\HeadMark;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * What a TRIGGER owns: which moment it answers, whether that moment really happened HERE, and WHAT IT
 * HANDS OVER. It starts nothing — it writes the work down — so everything checked here is what the
 * orchestrator will read at its next stop, and everything a trigger fails to write down is something the
 * agent will work out for itself and get wrong.
 */
final class TriggerTest extends TestCase
{
    private const string SESSION = 'trigger-test-session';

    private const string PROFILE = 'demo';

    private const string AGENT = 'reviewer';

    private const string PROCEDURE = 'review';

    private const string HEAD = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private string $root;

    private FakeGit $git;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        $this->root = realpath(sys_get_temp_dir()) . '/cc-trigger-' . uniqid('', true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=' . self::SESSION);

        $this->git = new FakeGit($this->root, head: self::HEAD);

        $this->bind('commit');

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
        // A builder checkpointing in its own lane raises this moment too, and lane commits are drafts by
        // design. Three lanes checkpointing per widget is fifteen reviews for work that lands as three
        // merges, each against a tree its author has already moved past.
        HeadMark::named($this->workspace(), 'commit')->movedTo(self::HEAD);

        $said = $this->commit();

        $this->assertSame([], $this->waiting(), 'nothing may be started for a commit that landed elsewhere');
        $this->assertSame('', $said, 'and nothing may be said about it either');
    }

    public function test_the_same_commit_seen_twice_is_answered_once(): void
    {
        $this->commit();
        $this->commit();

        $this->assertCount(1, $this->waiting(), 'one commit, one dispatch, however often the moment fires');
    }

    public function test_a_commit_that_moved_this_checkouts_head_is_dispatched_against_that_sha(): void
    {
        $this->commit();

        $entries = $this->waiting();

        $this->assertCount(1, $entries);
        $this->assertSame(self::HEAD, $entries[0]->subject, 'a sha is addressable, which is what a subject has to be');
    }

    public function test_the_work_written_down_names_the_moment_the_agent_and_the_procedure(): void
    {
        $this->commit();

        $work = $this->waiting()[0];

        $this->assertSame('commit', $work->moment, 'which moment asked for it');
        $this->assertSame(self::AGENT, $work->agent);
        $this->assertSame(self::PROCEDURE, $work->procedure, 'a hook writes down the whole job, since the stop that reads it has no other source');
    }

    public function test_the_brief_points_at_the_diff_rather_than_only_naming_the_sha(): void
    {
        $this->commit();

        $this->assertStringContainsString(
            'git show ' . self::HEAD,
            $this->brief(),
            'a subject the reader cannot reach is a name rather than a subject',
        );
    }

    public function test_a_tool_call_that_is_not_a_commit_is_passed_over(): void
    {
        $said = $this->fire(['tool_name' => 'Bash', 'tool_input' => ['command' => 'git status']]);

        $this->assertSame([], $this->waiting());
        $this->assertSame('', $said);
    }

    public function test_a_finished_worker_arrives_as_ONE_worker_rather_than_as_its_job_title(): void
    {
        $this->bind('worker-finished');
        $this->workerFinished();

        $this->assertSame(
            'general-purpose#agent_01HZ',
            $this->waiting()[0]->subject,
            'the bare type arrived as the literal word `general-purpose`: two different builders, one subject',
        );
    }

    public function test_a_finished_worker_is_pointed_at_what_it_actually_SAID(): void
    {
        $this->bind('worker-finished');
        $this->workerFinished();

        $this->assertStringContainsString(
            '/tmp/whatever.jsonl',
            $this->brief(),
            'the moment carries WHO stopped and never WHAT they said, so the brief must at least say where it is',
        );
    }

    public function test_a_worker_the_harness_named_nothing_about_is_not_dispatched_at_all(): void
    {
        $this->bind('worker-finished');

        $said = $this->fire(['hook_event_name' => 'SubagentStop']);

        $this->assertSame([], $this->waiting(), 'never spawn an agent to discover you had nothing to hand it');
        $this->assertStringContainsString('carried no subject', $said);
    }

    private function commit(): string
    {
        return $this->fire(['tool_name' => 'Bash', 'tool_input' => ['command' => 'git commit -m "a change"']]);
    }

    private function workerFinished(): string
    {
        return $this->fire([
            'hook_event_name' => 'SubagentStop',
            'agent_type' => 'general-purpose',
            'agent_id' => 'agent_01HZ',
            'transcript_path' => '/tmp/whatever.jsonl',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fire(array $payload): string
    {
        $io = new RecordingHookIO(
            ['hook_event_name' => 'PostToolUse', ...$payload, 'session_id' => self::SESSION],
            $this->git,
        );

        $hook = ($payload['hook_event_name'] ?? '') === 'SubagentStop'
            ? new WorkerFinishedTrigger($io)
            : new CommitTrigger($io);

        $hook->run([]);

        $said = [];

        foreach ($io->emitted as $response) {
            $said[] = $response->context->unwrapOr('');
        }

        return implode("\n", $said);
    }

    /**
     * The whole prompt the orchestrator would hand over for the first thing waiting.
     */
    private function brief(): string
    {
        return new Dispatcher($this->workspace(), $this->root)->briefFor($this->waiting()[0]);
    }

    /**
     * @return list<Dispatched>
     */
    private function waiting(): array
    {
        return Pending::inSession($this->workspace())->all();
    }

    private function workspace(): Workspace
    {
        return Workspace::ofSession($this->root, self::SESSION);
    }

    private function bind(string $moment): void
    {
        $profile = $this->root . '/.commandments/orchestrator/profiles/' . self::PROFILE;

        @mkdir($profile . '/procedures', 0777, true);
        @mkdir($profile . '/roles', 0777, true);
        file_put_contents($profile . '/profile.md', '# ' . self::PROFILE);
        file_put_contents($profile . '/procedures/' . self::PROCEDURE . '.md', 'Read the diff. Say what is unidiomatic.');
        file_put_contents($profile . '/roles/' . self::AGENT . '.md', 'You read, you do not write.');
        file_put_contents($profile . '/settings.json', json_encode([
            $moment => [new Duty(self::AGENT, self::PROCEDURE)->toDeclared()],
        ], JSON_PRETTY_PRINT));
    }
}
