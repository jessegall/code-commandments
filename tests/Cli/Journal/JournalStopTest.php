<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Hooks\Handlers\JournalReminder;
use JesseGall\CodeCommandments\Hooks\HookResponse;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;
use PHPUnit\Framework\TestCase;

/**
 * What the hook SAYS at a stop, and where those words come from. Every one of them is a file this package
 * ships and a project may rewrite, so the tests that matter are the two that a copy kept "as a fallback"
 * would pass anyway: the words follow the file, and emptying the file stops the hook holding the turn.
 *
 * A gate whose reason has been silenced is the case worth naming. Blocking with nothing to say is worse
 * than either alternative — the agent is stopped and told nothing about why — so silence takes the gate
 * with it.
 */
final class JournalStopTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-journal-stop-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function workspace(): Workspace
    {
        return Workspace::at($this->root, 'sess-1');
    }

    private function declares(string $work): void
    {
        Journal::inSession($this->workspace())
            ->file(new Entry(Kind::Agent, 'now', 't', 'm' . uniqid(), Option::some(Tag::Start), Tag::Start->marker() . ' ' . $work));
    }

    private function stop(): HookResponse
    {
        $io = new RecordingHookIO(['hook_event_name' => 'Stop', 'session_id' => 'sess-1'], new FakeGit($this->root));

        new JournalReminder($io)->run([]);

        return $io->emitted[0] ?? HookResponse::silent();
    }

    public function test_open_work_holds_the_stop_and_names_it(): void
    {
        $this->declares('making Drilldown a composition');

        $response = $this->stop();
        $said = $response->blockReason->unwrapOr('');

        $this->assertTrue($response->blockReason->isSome(), 'open work holds the turn');

        $this->assertStringContainsString('making Drilldown a composition', $said);
        $this->assertStringContainsString(Tag::End->marker(), $said, 'it says how to close the work');
        $this->assertStringContainsString('journal open', $said, 'and the command that lists it');
    }
}
