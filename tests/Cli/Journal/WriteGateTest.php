<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Hooks\Handlers\WriteGate;
use JesseGall\CodeCommandments\Hooks\HookResponse;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * Nothing is changed until the work is declared. A tool that names its file is refused before it writes;
 * the gate stays out of the way once a `[!start]` stands, and stays silent entirely in a session whose
 * messages were never recorded, where it could not be satisfied at all.
 */
final class WriteGateTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-writegate-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function journal(): Journal
    {
        return Journal::inSession(new Workspace($this->root, 'sess-1'));
    }

    /**
     * The gate enforces only where the recorder has demonstrably worked, so every case needs a session
     * that has been recording.
     */
    private function recording(): Journal
    {
        $journal = $this->journal();

        foreach (['one', 'two', 'three'] as $said) {
            $journal->file(new Entry(Kind::Agent, 'now', 't', uniqid(), Tag::parse($said), $said));
        }

        return $journal;
    }

    private function declareWork(Journal $journal): void
    {
        $journal->file(new Entry(Kind::Agent, 'now', 't', 'm', Tag::parse('[!start] the reader'), '[!start] the reader'));
    }

    /**
     * @return list<HookResponse>
     */
    private function edit(): array
    {
        $io = new RecordingHookIO([
            'hook_event_name' => 'PreToolUse',
            'session_id' => 'sess-1',
            'tool_name' => 'Edit',
            'tool_input' => ['file_path' => $this->root . '/src/Thing.php'],
        ], new FakeGit($this->root));

        new WriteGate($io)->run([]);

        return $io->emitted;
    }

    public function test_a_write_with_no_work_declared_is_refused(): void
    {
        $this->recording();

        $emitted = $this->edit();

        $this->assertCount(1, $emitted);
        $this->assertStringContainsString('[!start]', $emitted[0]->blockReason->unwrap());
        $this->assertStringContainsString('journal instructions', $emitted[0]->blockReason->unwrap());
    }

    public function test_a_write_is_allowed_once_the_work_is_declared(): void
    {
        $this->declareWork($this->recording());

        $this->assertSame([], $this->edit());
    }

    /**
     * Closing the work closes the permission with it — the next piece must be declared in its turn.
     */
    public function test_closing_the_work_closes_the_gate_again(): void
    {
        $journal = $this->recording();
        $this->declareWork($journal);
        $journal->file(new Entry(Kind::Agent, 'now', 't', 'm2', Tag::parse('[!end] the reader'), '[!end] the reader'));

        $this->assertCount(1, $this->edit());
    }

    /**
     * A session whose messages were never recorded has no way to open a span, so a gate that enforced
     * there would refuse every write for ever with no answer available.
     */
    public function test_it_stays_silent_where_it_could_never_be_satisfied(): void
    {
        $this->assertSame([], $this->edit());
    }

    /**
     * The write has already happened by the time a shell command is judged, so refusing it would cost the
     * user a line in their terminal and stop nothing. The agent is told; the user is not made to watch.
     */
    public function test_a_shell_write_is_a_quiet_word_not_a_refusal(): void
    {
        $this->recording();
        file_put_contents($this->root . '/Thing.php', '<?php');

        $io = new RecordingHookIO([
            'hook_event_name' => 'PostToolUse',
            'session_id' => 'sess-1',
            'tool_name' => 'Bash',
            'tool_input' => ['command' => 'echo x > Thing.php'],
        ], new FakeGit($this->root));

        new WriteGate($io)->run([]);

        $blocking = array_filter($io->emitted, fn (HookResponse $response) => $response->blockReason->isSome());

        $this->assertSame([], $blocking, 'a shell write must never block — it has already happened');
    }

    /**
     * A checkout, a merge or a rebase rewrites a great many judged files without anybody editing one.
     * Those arrive already committed, so they are not the agent's work and are not refused.
     */
    public function test_files_git_itself_rewrote_are_not_the_agents_work(): void
    {
        $this->recording();

        $io = new RecordingHookIO([
            'hook_event_name' => 'PostToolUse',
            'session_id' => 'sess-1',
            'tool_name' => 'Bash',
            'tool_input' => ['command' => 'git merge --ff-only plan/journal'],
        ], new FakeGit($this->root));

        new WriteGate($io)->run([]);

        $this->assertSame([], $io->emitted);
    }

    /**
     * A tool that changes nothing is not the gate's business.
     */
    public function test_a_reading_tool_is_never_refused(): void
    {
        $this->recording();

        $io = new RecordingHookIO([
            'hook_event_name' => 'PreToolUse',
            'session_id' => 'sess-1',
            'tool_name' => 'Read',
            'tool_input' => ['file_path' => $this->root . '/src/Thing.php'],
        ], new FakeGit($this->root));

        new WriteGate($io)->run([]);

        $this->assertSame([], $io->emitted);
    }
}
