<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Hooks\Handlers\CompactionGate;
use JesseGall\CodeCommandments\Hooks\Handlers\JournalRecorder;
use JesseGall\CodeCommandments\Hooks\HookResponse;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The two moments that decide what a compaction takes: the recorder filing each message as it streams, and
 * the gate holding the first automatic compaction then writing the summariser its instructions.
 */
final class CompactionGateTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-gate-' . uniqid('', true);
        mkdir($this->root, 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<HookResponse>
     */
    private function fire(object $hook, array $payload): array
    {
        $io = new RecordingHookIO([...$payload, 'session_id' => 'sess-1'], new FakeGit($this->root));

        new ($hook::class)($io)->run([]);

        return $io->emitted;
    }

    private function journal(): Journal
    {
        return Journal::inSession(new Workspace($this->root, 'sess-1'));
    }

    private function flush(string $delta, int $index, bool $final): array
    {
        return [
            'hook_event_name' => 'MessageDisplay',
            'transcript_path' => '/tmp/sess-1.jsonl',
            'turn_id' => 'turn-1',
            'message_id' => 'msg-' . $index,
            'index' => $index,
            'final' => $final,
            'delta' => $delta,
        ];
    }

    /**
     * A delta carries only the lines completed since the last flush, so the tag exists in the FIRST one.
     * Filing at `final` would read the tail of a long message and record it as untagged.
     */
    public function test_a_message_is_filed_from_the_flush_that_carries_its_tag(): void
    {
        $recorder = new JournalRecorder;

        $this->fire($recorder, $this->flush('[discovery] the pattern already exists', 0, false));
        $this->fire($recorder, [...$this->flush('and the rest of the message', 1, true), 'message_id' => 'msg-0']);

        $entries = $this->journal()->entries();

        $this->assertCount(1, $entries);
        $this->assertSame(Tag::Discovery, $entries[0]->tag->unwrap());
        $this->assertSame('[discovery] the pattern already exists', $entries[0]->text);
    }

    public function test_the_users_own_words_are_filed(): void
    {
        $this->fire(new JournalRecorder, [
            'hook_event_name' => 'UserPromptSubmit',
            'prompt' => 'motion.ts is FORBIDDEN',
            'transcript_path' => '/tmp/sess-1.jsonl',
        ]);

        $entries = $this->journal()->entries();

        $this->assertCount(1, $entries);
        $this->assertSame(Kind::User, $entries[0]->kind);
        $this->assertSame('motion.ts is FORBIDDEN', $entries[0]->text);
    }

    /**
     * The first automatic compaction buys the agent one turn to record what only it knows.
     */
    public function test_the_first_automatic_compaction_is_held(): void
    {
        $emitted = $this->fire(new CompactionGate, ['hook_event_name' => 'PreCompact', 'trigger' => 'auto']);

        $this->assertCount(1, $emitted);
        $this->assertTrue($emitted[0]->blockReason->isSome());
        $this->assertStringContainsString('journal remember', $emitted[0]->blockReason->unwrap());
        $this->assertTrue($this->journal()->isPreparedForCompaction());
    }

    /**
     * The attempt after that must go through — a compaction held forever is a session that dies at the
     * hard context wall instead.
     */
    public function test_the_next_attempt_is_instructed_rather_than_held(): void
    {
        $journal = $this->journal();
        $journal->file(new Entry(Kind::Agent, 'now', 't', 'm', Tag::parse('[pinned] judge is banned until the build is done'), '[pinned] judge is banned until the build is done'));
        $journal->file(new Entry(Kind::Agent, 'now', 't', 'm2', Tag::parse('[start] making Drilldown a composition'), '[start] making Drilldown a composition'));

        $gate = new CompactionGate;
        $this->fire($gate, ['hook_event_name' => 'PreCompact', 'trigger' => 'auto']);
        $emitted = $this->fire($gate, ['hook_event_name' => 'PreCompact', 'trigger' => 'auto']);

        $this->assertCount(1, $emitted);
        $this->assertTrue($emitted[0]->blockReason->isNone());

        $instructions = $emitted[0]->json('PreCompact');

        $this->assertStringContainsString('PRESERVE DECISIONS', $instructions);
        $this->assertStringContainsString('judge is banned until the build is done', $instructions);
        $this->assertStringContainsString('making Drilldown a composition', $instructions);
    }

    /**
     * A compaction the user asked for themselves is theirs, and is never held up.
     */
    public function test_a_manual_compaction_is_not_the_gates_business(): void
    {
        $this->assertSame(
            [],
            $this->fire(new CompactionGate, ['hook_event_name' => 'PreCompact', 'trigger' => 'manual']),
        );
    }
}
