<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;
use PHPUnit\Framework\TestCase;

/**
 * The index the journal keeps over its own transcript — the tag a message wore, the work left open, the
 * facts pinned to survive a compaction, and the boundaries the chunks are counted between.
 */
final class JournalTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-journal-' . uniqid('', true);
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function journal(): Journal
    {
        return Journal::inSession(new Workspace($this->root, 'sess-1'));
    }

    private function agent(string $text): Entry
    {
        return new Entry(Kind::Agent, '2026-08-29T17:00:00Z', 'turn-1', uniqid('msg-', true), Tag::parse($text), $text);
    }

    public function test_a_tag_is_read_off_the_front_of_a_message(): void
    {
        $this->assertSame(Tag::Discovery, Tag::parse('[d] the pattern already exists')->unwrap());
        $this->assertSame(Tag::Correction, Tag::parse('[!] I had that backwards')->unwrap());
        $this->assertSame(Tag::Start, Tag::parse('[s] making Drilldown a composition')->unwrap());
        $this->assertSame(Tag::Done, Tag::parse('[✓] 645 green')->unwrap());
    }

    /**
     * The pinned mark is two characters, and the one-character correction is its prefix — so the longer
     * one must win, or every pinned fact would file itself as a correction.
     */
    public function test_the_pinned_mark_is_not_mistaken_for_a_correction(): void
    {
        $this->assertSame(Tag::Pinned, Tag::parse('[!!] motion.ts is FORBIDDEN')->unwrap());
        $this->assertTrue(Tag::parse('[!!] motion.ts is FORBIDDEN')->unwrap()->isPinned());
        $this->assertFalse(Tag::parse('[!] a correction')->unwrap()->isPinned());
    }

    public function test_an_untagged_message_carries_no_tag(): void
    {
        $this->assertTrue(Tag::parse('just prose')->isNone());
        $this->assertTrue(Tag::parse('[z] not a tag')->isNone());
        $this->assertTrue(Tag::parse('[unclosed')->isNone());
        $this->assertTrue(Tag::parse('')->isNone());
    }

    public function test_an_entry_survives_the_round_trip(): void
    {
        $entry = $this->agent('[d] a discovery');
        $back = Entry::fromLine($entry->toLine())->unwrap();

        $this->assertSame(Kind::Agent, $back->kind);
        $this->assertSame('[d] a discovery', $back->text);
        $this->assertSame(Tag::Discovery, $back->tag->unwrap());
        $this->assertSame($entry->messageId, $back->messageId);
    }

    /**
     * A filed line is ONE line — the rest of the message stays in the transcript, which is the record.
     */
    public function test_only_the_first_line_of_a_message_is_filed(): void
    {
        $entry = new Entry(Kind::Agent, 'now', 't', 'm', Option::none(), "\n\nthe first real line\nand more\nand more");

        $this->assertSame('the first real line', Entry::fromLine($entry->toLine())->unwrap()->text);
    }

    public function test_a_hand_edited_line_is_skipped_rather_than_fatal(): void
    {
        $this->assertTrue(Entry::fromLine('some human wrote this')->isNone());
    }

    public function test_it_files_and_reads_back_in_order(): void
    {
        $journal = $this->journal();
        $journal->file($this->agent('[s] first'));
        $journal->file($this->agent('[d] second'));

        $entries = $journal->entries();

        $this->assertCount(2, $entries);
        $this->assertSame('[s] first', $entries[0]->text);
        $this->assertSame('[d] second', $entries[1]->text);
    }

    /**
     * Unfinished work is the one thing in the journal that is live state, and it falls out of the pairing.
     */
    public function test_work_started_and_not_finished_is_open(): void
    {
        $journal = $this->journal();
        $journal->file($this->agent('[s] the reader'));
        $journal->file($this->agent('[d] a discovery in the middle'));
        $journal->file($this->agent('[e] the reader'));
        $journal->file($this->agent('[s] the digest'));

        $open = $journal->openSpans();

        $this->assertCount(1, $open);
        $this->assertSame('[s] the digest', $open[0]->text);
    }

    public function test_nested_work_closes_innermost_first(): void
    {
        $journal = $this->journal();
        $journal->file($this->agent('[s] outer'));
        $journal->file($this->agent('[s] inner'));
        $journal->file($this->agent('[e] inner'));

        $open = $journal->openSpans();

        $this->assertCount(1, $open);
        $this->assertSame('[s] outer', $open[0]->text);
    }

    public function test_pinned_facts_are_gathered(): void
    {
        $journal = $this->journal();
        $journal->file($this->agent('[i] routine'));
        $journal->file($this->agent('[!!] motion.ts is FORBIDDEN'));
        $journal->file($this->agent('[!!] judge is banned until the build is done'));

        $this->assertSame(
            ['[!!] motion.ts is FORBIDDEN', '[!!] judge is banned until the build is done'],
            array_map(fn (Entry $entry) => $entry->text, $journal->pinned()),
        );
    }

    public function test_a_compaction_moves_the_chunk_on(): void
    {
        $journal = $this->journal();

        $this->assertSame(0, $journal->chunk());

        $journal->markCompaction('2026-08-29T17:09:40Z', 'the conversation, rewritten');

        $this->assertSame(1, $journal->chunk());
        $this->assertStringContainsString('the conversation, rewritten', $journal->entries()[0]->text);
    }

    /**
     * The gate cancels one compaction attempt, never the next — a session that could never compact would
     * be worse than one that compacts badly.
     */
    public function test_preparing_is_remembered_until_the_compaction_happens(): void
    {
        $journal = $this->journal();

        $this->assertFalse($journal->isPreparedForCompaction());

        $journal->prepare();
        $this->assertTrue($journal->isPreparedForCompaction());

        $journal->markCompaction('now', 'summary');
        $this->assertFalse($journal->isPreparedForCompaction());
    }

    public function test_it_records_the_transcript_it_indexes_and_the_session_chain(): void
    {
        $journal = $this->journal();
        $journal->follow('/tmp/sess-1.jsonl', 'sess-1', 'sess-0');

        $this->assertSame('/tmp/sess-1.jsonl', $journal->transcript()->unwrap());
    }

    /**
     * A hook appends on every message flush, so the file must not grow without end.
     */
    public function test_the_index_is_bounded(): void
    {
        $journal = $this->journal();

        for ($i = 0; $i < 4010; $i++) {
            $journal->file($this->agent("[i] message {$i}"));
        }

        $entries = $journal->entries();

        $this->assertCount(4000, $entries);
        $this->assertSame('[i] message 4009', end($entries)->text);
    }
}
