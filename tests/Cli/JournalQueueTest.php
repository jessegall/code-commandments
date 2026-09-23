<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Hooks\JournalAnswer;
use JesseGall\CodeCommandments\Cli\Hooks\JournalQueue;
use JesseGall\CodeCommandments\Cli\Hooks\JournalRaise;
use PHPUnit\Framework\TestCase;

final class JournalQueueTest extends TestCase
{
    private string $queue;

    protected function setUp(): void
    {
        $this->queue = sys_get_temp_dir() . '/journal-queue-' . bin2hex(random_bytes(4));
        putenv(JournalQueue::PATH . "={$this->queue}");
    }

    protected function tearDown(): void
    {
        putenv(JournalQueue::PATH);
        @unlink($this->queue);
    }

    public function test_advice_becomes_a_nudge_per_thing_it_says_and_its_event_is_raised(): void
    {
        JournalQueue::fromEnvironment()->unwrap()->tell(new JournalAnswer(
            whisper: "Code Commandments — before you commit: judge what you changed.\nRun it once.",
            raise: new JournalRaise('sin-found', "app/Order.php:12 ArrayBag\napp/Order.php:30 FeatureEnvy"),
        ));

        $this->assertSame([
            ['nudge', 'create', 'Code Commandments — before you commit — judge what you changed.', '--brief', "Code Commandments — before you commit: judge what you changed. · Run it once."],
            ['plugin', 'raise', 'code-commandments', 'sin-found', "app/Order.php:12 ArrayBag · app/Order.php:30 FeatureEnvy"],
        ], $this->queued());
    }

    public function test_nothing_to_say_writes_nothing(): void
    {
        JournalQueue::fromEnvironment()->unwrap()->tell(new JournalAnswer());

        $this->assertFileDoesNotExist($this->queue);
    }

    public function test_there_is_no_queue_the_journal_did_not_name(): void
    {
        putenv(JournalQueue::PATH);

        $this->assertTrue(JournalQueue::fromEnvironment()->isNone());
    }

    /**
     * The queued lines split into words the way the journal splits them, with Python's shlex.
     *
     * @return list<list<string>>
     */
    private function queued(): array
    {
        $split = shell_exec('python3 -c ' . escapeshellarg('import json, shlex, sys; print(json.dumps([shlex.split(l) for l in open(sys.argv[1]) if l.strip()]))') . ' ' . escapeshellarg($this->queue));

        return json_decode((string) $split, true);
    }
}
