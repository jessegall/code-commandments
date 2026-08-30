<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\Dispatched;
use JesseGall\CodeCommandments\Cli\Orchestration\Duty;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\Pending;
use JesseGall\CodeCommandments\Cli\Orchestration\QueueCommand;
use JesseGall\CodeCommandments\Hooks\Handlers\DispatchReminder;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The work a moment asked for and nobody has started, and the stop that holds until somebody has. This is
 * where a trigger becomes an agent now: nothing is spawned behind anybody's back, so the ONLY thing that
 * makes a dispatch happen is a stop the orchestrator cannot walk past.
 */
final class PendingDispatchTest extends TestCase
{
    private const string SESSION = 'pending-test-session';

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
        $this->root = realpath(sys_get_temp_dir()) . '/cc-pending-' . uniqid('', true);
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

    public function test_a_stop_with_nothing_waiting_is_not_held(): void
    {
        $this->assertSame('', $this->stop(), 'a hold nobody owes is how a reader learns to skim the block that will eventually matter');
    }

    public function test_a_stop_is_TOLD_while_a_dispatch_is_owed(): void
    {
        $this->pending()->add($this->work('sha-a'));

        // Blocked, never nudged. A nudge is how a dispatch gets skipped — that is exactly what happens to
        // the routine — and work nobody was made to notice is work that did not happen.
        $said = $this->stop();

        $this->assertStringContainsString('1 dispatch(es) waiting', $said, 'a COUNT, which is all the orchestrator can act on');
        $this->assertStringContainsString('scheduler', $said, 'and the one command that does something about it');
    }

    public function test_the_orchestrator_is_told_a_count_and_not_the_contents(): void
    {
        $this->pending()->add($this->work('sha-a'));

        $said = $this->stop();

        // The DETAIL goes to whoever can act on it, and that is not the orchestrator. Its only move is
        // to start a scheduler, whatever the queue holds — so a subject, a moment or an agent name in
        // this message is detail delivered to the one party that cannot use it, while the scheduler,
        // which reads the queue itself, is told nothing until it is summoned.
        $this->assertStringNotContainsString('sha-a', $said, 'no subject: it changes nothing the orchestrator decides');
        $this->assertStringNotContainsString('ponytail', $said, 'no agent name, for the same reason');
        $this->assertStringNotContainsString('Read the diff. Say what is unidiomatic.', $said, 'and certainly not the procedure');
    }

    public function test_it_names_the_command_that_does_something_about_it(): void
    {
        $this->pending()->add($this->work('sha-a'));

        $this->assertStringContainsString('scheduler', $this->stop(), 'the one command the orchestrator can act on');
    }

    public function test_saying_it_was_dispatched_releases_the_stop(): void
    {
        $this->pending()->add($this->work('sha-a'));

        $this->assertSame(0, $this->queue(['dispatched', self::AGENT]));
        $this->assertTrue($this->pending()->isEmpty());
        $this->assertSame('', $this->stop(), 'and the stop is a stop again');
    }

    public function test_claiming_to_have_dispatched_what_nobody_asked_for_is_refused(): void
    {
        $this->assertSame(Console::REFUSED, $this->queue(['dispatched', 'nobody']), 'a release that always succeeds releases holds that were never taken');
    }

    public function test_the_same_work_arriving_twice_is_owed_once(): void
    {
        $this->pending()->add($this->work('sha-a'));
        $this->pending()->add($this->work('sha-a'));

        $this->assertCount(1, $this->pending()->all(), 'a moment that fires twice on one subject is one piece of work');
    }

    public function test_a_second_subject_STACKS_rather_than_replacing_the_first(): void
    {
        $this->pending()->add($this->work('sha-a'));
        $this->pending()->add($this->work('sha-b'));

        $this->assertCount(2, $this->pending()->all(), 'dropping one means a commit nobody reviewed, which is invisible');
    }

    public function test_it_tells_the_orchestrator_and_never_holds_it(): void
    {
        $this->pending()->add($this->work('sha-a'));

        // Said ONCE, then quiet. A queue does not change what the orchestrator can do about it, so the
        // second telling adds nothing and the tenth teaches the reader to skim — which is what happened:
        // nine lines repeated a dozen times, answered a dozen times with "Declining", unread by the
        // fourth. And it never HOLDS, because a hold is answered by dispatching and an agent that cannot
        // answers by stopping, which fires the stop again.
        $this->assertStringContainsString('waiting', $this->stop(), 'it says so');
        $this->assertSame('', $this->stop(), 'and does not say it again on the next stop');
        $this->assertSame('', $this->stop(), 'nor the one after');
    }

    public function test_the_brief_can_be_asked_for_on_its_own(): void
    {
        $this->pending()->add($this->work('sha-a'));

        $this->queue(['brief', self::AGENT]);

        $this->assertStringContainsString('Read the diff. Say what is unidiomatic.', $this->said());
    }

    public function test_a_row_survives_a_pointer_that_carries_a_newline(): void
    {
        // The source is prose somebody else wrote; a row is one line with a fixed number of fields, so a
        // newline in it would silently become a different row — or no row at all.
        $this->pending()->add(new Dispatched('10:00', 'commit', 'sha-a', self::AGENT, self::PROCEDURE, "look\nhere"));

        $this->assertCount(1, $this->pending()->all());
        $this->assertSame('look here', $this->pending()->all()[0]->source);
    }

    private function work(string $subject): Dispatched
    {
        return new Dispatched('2026-08-30 04:00:00', 'commit', $subject, self::AGENT, self::PROCEDURE, 'git show ' . $subject);
    }

    private function pending(): Pending
    {
        return Pending::inSession($this->workspace());
    }

    /**
     * Fire a Stop, and answer with whatever held it.
     */
    private function stop(): string
    {
        $io = new RecordingHookIO(['hook_event_name' => 'Stop', 'session_id' => self::SESSION], new FakeGit($this->root));

        new DispatchReminder($io)->run([]);

        $said = [];

        // Whatever it said, by whichever channel. It TELLS now rather than holding, so reading only the
        // block reason would report silence from a hook that spoke.
        foreach ($io->emitted as $response) {
            $said[] = $response->blockReason->unwrapOr('') . $response->context->unwrapOr('');
        }

        return implode("\n", $said);
    }

    /**
     * @param  list<string>  $args
     */
    private function queue(array $args): int
    {
        return new QueueCommand(new RecordingHookIO([], new FakeGit($this->root)), new Console($this->out))
            ->run(Input::of('queue', $args));
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

    private function writeProfile(): void
    {
        $profile = $this->root . '/.commandments/orchestrator/profiles/' . self::PROFILE;

        @mkdir($profile . '/procedures', 0777, true);
        @mkdir($profile . '/roles', 0777, true);
        file_put_contents($profile . '/profile.md', '# ' . self::PROFILE);
        file_put_contents($profile . '/procedures/' . self::PROCEDURE . '.md', 'Read the diff. Say what is unidiomatic.');
        file_put_contents($profile . '/roles/' . self::AGENT . '.md', 'You read, you do not write.');
        file_put_contents($profile . '/settings.json', json_encode([
            'commit' => [new Duty(self::AGENT, self::PROCEDURE)->toDeclared()],
        ], JSON_PRETTY_PRINT));
    }
}
