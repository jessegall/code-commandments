<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Queue;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * What one agent still has to read. The queue exists to stop two agents starting in one lane — and it
 * once committed that very collision itself, because it READ "idle" and then WROTE the claim as two
 * acts, so two hook processes firing close together both read idle and both spawned.
 *
 * A claim that is read and then written is not a claim, which is a fact about PROCESSES: the proof here
 * runs a second PHP process against the same queue, because that is the shape of the bug.
 */
final class QueueTest extends TestCase
{
    private const string SESSION = 'queue-test';

    private const string AGENT = 'reviewer';

    /**
     * How long the blocked process is watched before it is believed to be blocked. It writes its own
     * "I am here" mark first, so this measures the gap between reaching `take` and answering — not the
     * time PHP takes to boot.
     */
    private const int WATCH_MICROSECONDS = 250_000;

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-queue-' . uniqid('', true);
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_second_process_cannot_read_the_lane_while_another_is_claiming_it(): void
    {
        $queue = $this->queue();
        $queue->take('sha-a');
        $queue->finishAndTakeNext();

        // Standing in for the OTHER trigger, mid-claim: it holds the lock and has not written yet, which
        // is exactly the window the unguarded version answered questions in.
        $lock = fopen($this->lockFile(), 'c');
        $this->assertNotFalse($lock);
        flock($lock, LOCK_EX);

        $other = $this->takeInAnotherProcess('sha-b');
        $other->waitToReach();

        usleep(self::WATCH_MICROSECONDS);

        $this->assertFalse($other->hasAnswered(), 'it read the lane while somebody else was claiming it');

        flock($lock, LOCK_UN);
        fclose($lock);

        $this->assertSame('took', $other->answer(), 'and once the claim is settled it may look');
        $this->assertSame('sha-b', $this->queue()->running());
    }

    public function test_a_subject_arriving_while_the_agent_reads_waits_rather_than_starting_a_second(): void
    {
        $queue = $this->queue();

        $this->assertTrue($queue->take('sha-a'), 'an idle lane starts');
        $this->assertFalse($queue->take('sha-b'), 'a busy one does not');
        $this->assertSame('sha-a', $queue->running());
        $this->assertSame(['sha-b'], $queue->waiting(), 'dropping it would be worse: a commit nobody reviewed is invisible');
    }

    public function test_the_first_agent_starts_even_though_the_session_folder_is_not_there_yet(): void
    {
        // The lock is opened BEFORE anything is written, so a folder that does not exist yet used to
        // answer "cannot lock" — which the caller reads as "somebody is already reading", and reports as
        // a queue behind an agent that was never started.
        $this->assertTrue($this->queue()->take('sha-a'), 'the very first commit of a session still starts its reader');
    }

    public function test_finishing_with_nothing_waiting_leaves_the_lane_idle(): void
    {
        $queue = $this->queue();
        $queue->take('sha-a');

        $this->assertTrue($queue->finishAndTakeNext()->isNone(), 'nothing is handed back');
        $this->assertSame('', $queue->running());
        $this->assertTrue($queue->take('sha-b'), 'and the lane is free for the next');
    }

    public function test_finishing_hands_back_the_oldest_waiting_subject_and_takes_it_in_the_same_act(): void
    {
        $queue = $this->queue();
        $queue->take('sha-a');
        $queue->take('sha-b');
        $queue->take('sha-c');

        $this->assertSame('sha-b', $queue->finishAndTakeNext()->unwrapOr(''), 'oldest first');
        $this->assertSame('sha-b', $queue->running(), 'and it is RUNNING, so nothing can slip in and start it twice');
        $this->assertSame(['sha-c'], $queue->waiting());
    }

    public function test_the_conversation_is_minted_once_and_read_back_the_same_by_the_next_reader(): void
    {
        $minted = $this->queue()->conversation();

        $this->assertNotSame('', $minted);
        $this->assertSame($minted, $this->queue()->conversation(), 'a fresh reader of the same lane gets the SAME conversation');
    }

    public function test_a_lane_that_has_never_run_holds_no_conversation(): void
    {
        $this->assertFalse($this->queue()->hasConversation(), 'nothing to resume before anything has started');

        $this->queue()->conversation();

        $this->assertTrue($this->queue()->hasConversation());
    }

    public function test_two_agents_keep_two_lanes(): void
    {
        Queue::forAgent($this->workspace(), 'reviewer')->take('sha-a');

        $this->assertTrue(Queue::forAgent($this->workspace(), 'auditor')->take('sha-a'), 'one agent reading does not hold up another');
    }

    private function workspace(): Workspace
    {
        return Workspace::at($this->root, self::SESSION);
    }

    private function queue(): Queue
    {
        return Queue::forAgent($this->workspace(), self::AGENT);
    }

    private function lockFile(): string
    {
        return $this->workspace()->path('.queue-' . self::AGENT) . '.lock';
    }

    /**
     * Ask a SEPARATE PHP process to take $subject on the same queue — the shape of the bug, since two
     * hooks firing close together are two processes and no amount of in-process care would have helped.
     */
    private function takeInAnotherProcess(string $subject): OtherProcess
    {
        $other = new OtherProcess($this->root . '/reached', $this->root . '/answer');

        $script = $this->root . '/take.php';

        file_put_contents($script, sprintf(
            '<?php require %s; file_put_contents(%s, "yes"); $took = %s::forAgent(%s::at(%s, %s), %s)->take(%s); '
                . 'file_put_contents(%s, $took ? "took" : "queued");',
            var_export(dirname(__DIR__, 3) . '/vendor/autoload.php', true),
            var_export($other->reached, true),
            var_export(Queue::class, true),
            var_export(Workspace::class, true),
            var_export($this->root, true),
            var_export(self::SESSION, true),
            var_export(self::AGENT, true),
            var_export($subject, true),
            var_export($other->answer, true),
        ));

        exec(sprintf('%s %s > /dev/null 2>&1 &', escapeshellarg(PHP_BINARY), escapeshellarg($script)));

        return $other;
    }
}
