<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Agent;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Hooks\Handlers\JournalRecorder;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * A worker keeps its OWN journal. Before this, the recorder was silenced inside a subagent, so everything
 * a worker decided died with its transcript — one session counted 728 agent entries, all the
 * orchestrator's, while three workers ran ~180 tool calls and left two traces, both inside messages the
 * orchestrator retyped by hand.
 */
final class WorkerJournalTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-wj-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function says(string $text, array $extra = []): void
    {
        $io = new RecordingHookIO([
            'hook_event_name' => 'MessageDisplay',
            'session_id' => 'sess-1',
            'turn_id' => 't',
            'message_id' => 'm' . uniqid(),
            'index' => 0,
            'final' => true,
            'delta' => $text,
            ...$extra,
        ], new FakeGit($this->root));

        new JournalRecorder($io)->run([]);
    }

    private function workspace(): Workspace
    {
        return Workspace::at($this->root, 'sess-1');
    }

    /**
     * @return list<string>
     */
    private function texts(Journal $journal): array
    {
        return array_map(fn ($entry) => $entry->text, $journal->entries());
    }

    public function test_a_worker_records_into_its_own_journal(): void
    {
        $this->says('[!discovery] the enum was never the cause', ['agent_id' => 'a999', 'agent_type' => 'walker']);

        $mine = Journal::ofAgent($this->workspace(), new Agent('a999', 'walker'));

        $this->assertSame(['[!discovery] the enum was never the cause'], $this->texts($mine));
    }

    /**
     * The property that matters most. A subagent's payload carries the PARENT's session id, so filing by
     * session would mix every worker's entries into the orchestrator's with no way to tell them apart.
     */
    public function test_a_workers_words_never_land_in_the_orchestrators_journal(): void
    {
        $this->says('[!discovery] the enum was never the cause', ['agent_id' => 'a999', 'agent_type' => 'walker']);

        $this->assertSame([], $this->texts(Journal::inSession($this->workspace())));
    }

    public function test_the_orchestrators_own_words_stay_in_the_sessions_journal(): void
    {
        $this->says('[!start] the port');

        $this->assertSame(['[!start] the port'], $this->texts(Journal::inSession($this->workspace())));
    }

    /**
     * Two workers are two records. Keyed on the agent, so a standing auditor on its sixth sweep reads
     * back its own and nobody else's.
     */
    public function test_two_workers_keep_separate_records(): void
    {
        $this->says('[!discovery] the walker found it', ['agent_id' => 'a111', 'agent_type' => 'walker']);
        $this->says('[!discovery] the auditor found something else', ['agent_id' => 'a222', 'agent_type' => 'auditor']);

        $this->assertSame(['[!discovery] the walker found it'], $this->texts(Journal::ofAgent($this->workspace(), new Agent('a111', 'walker'))));
        $this->assertSame(['[!discovery] the auditor found something else'], $this->texts(Journal::ofAgent($this->workspace(), new Agent('a222', 'auditor'))));
    }

    public function test_a_workers_journal_sits_under_the_session_keyed_by_agent(): void
    {
        $this->assertStringEndsWith(
            '/sessions/' . Workspace::keyFor('sess-1') . '/agents/a999/.journal',
            $this->workspace()->agentPath(new Agent('a999', 'walker'), '.journal'),
        );
    }
}
