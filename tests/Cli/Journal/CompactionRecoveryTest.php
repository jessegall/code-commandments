<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Hooks\Handlers\CompactionReminder;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;
use PHPUnit\Framework\TestCase;

/**
 * What a compacted agent WAKES to. The recovery used to be three commands it was told to run, and a real
 * compaction measured it running none of them — so the reads happen in the hook and the output is the
 * injection. These prove the two things that makes true: that the content is there without being fetched,
 * and that the block is small enough to arrive, which the 50KB pin dump it replaces was not.
 */
final class CompactionRecoveryTest extends TestCase
{
    /**
     * The measured ceiling from {@see CompactionReminder}. Restated here rather than exposed, because a
     * test that reads the constant it is checking proves only that the constant equals itself.
     */
    private const int ARRIVES = 2000;

    private const string SESSION = 'sess-recover';

    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-recover-' . uniqid('', true);
        mkdir($this->root, 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_open_work_arrives_as_content_rather_than_as_a_command_to_fetch_it(): void
    {
        $this->file(Tag::Start, 'making Drilldown a composition');

        $woken = $this->wake();

        $this->assertStringContainsString('making Drilldown a composition', $woken);
        $this->assertStringContainsString('OPEN WORK', $woken);
    }

    /**
     * The failure this replaces: the block named the reads and the agent did none of them. A pointer to
     * the rest of the record is fine — an errand standing between it and the finding is not.
     */
    public function test_the_recovery_is_not_an_errand(): void
    {
        $this->file(Tag::Start, 'rewriting the board');

        $woken = $this->wake();

        $this->assertStringNotContainsString('BEFORE your next substantive step', $woken);
        $this->assertStringContainsString('journal --back=1', $woken); // the REST is still pointed at.
    }

    /**
     * 55 pinned facts measured 50,053 bytes and arrived as a 2KB preview — about 4% read. Whatever else
     * the block carries, it has to fit in what a hook injection actually delivers.
     */
    public function test_a_session_that_pinned_everything_still_wakes_to_a_block_that_fits(): void
    {
        for ($at = 0; $at < 55; $at++) {
            $this->file(Tag::Pinned, "fact {$at}: " . str_repeat('a decision that was made and written down. ', 20));
        }

        for ($at = 0; $at < 20; $at++) {
            $this->file(Tag::Start, "span {$at}: " . str_repeat('a piece of work left open. ', 20));
        }

        $woken = $this->wake();

        $this->assertLessThanOrEqual(self::ARRIVES, strlen($woken));
        $this->assertStringContainsString('OPEN WORK', $woken); // The section that matters is not the one dropped.
    }

    /**
     * A truncated list must read as truncated. A block that shows six of twenty spans without saying so
     * is the same failure as the summary that stated a guess as a finding.
     */
    public function test_a_shortened_list_says_how_much_it_left_out(): void
    {
        for ($at = 0; $at < 9; $at++) {
            $this->file(Tag::Start, "span {$at}");
        }

        $this->assertStringContainsString('of 9, newest last', $this->wake());
    }

    /**
     * The stretch the summary replaced is one chunk BACK: the harness writes the boundary and the summary
     * before it re-fires `SessionStart`, so the stretch asked about must be the one that just ended.
     */
    public function test_a_tag_said_in_the_replaced_stretch_and_never_filed_is_named(): void
    {
        $this->transcript(
            ['type' => 'assistant', 'timestamp' => '2026-08-29T14:02:00Z', 'message' => ['content' => [['type' => 'text', 'text' => "[!start] wiring the receipt\nand then some prose"]]]],
            ['type' => 'system', 'subtype' => 'compact_boundary', 'content' => 'Conversation compacted'],
        );

        $woken = $this->wake();

        $this->assertStringContainsString('NEVER FILED', $woken);
        $this->assertStringContainsString('[!start] wiring the receipt', $woken);
    }

    /**
     * The second finding: a record that keeps an inference without its provenance is worse than one that
     * drops it. What was read from the index is marked as read, and the summary is named as the paraphrase
     * it is — so a judgement it froze is not mistaken for a measurement.
     */
    public function test_what_was_read_is_marked_off_from_what_the_summary_believes(): void
    {
        $woken = $this->wake();

        $this->assertStringContainsString('Everything above came from the record', $woken);
        $this->assertStringContainsString('belief', $woken);
    }

    /**
     * A session with nothing recorded wakes to the skill reminder and the pointer, and no empty headings.
     */
    public function test_a_session_with_nothing_recorded_carries_no_empty_sections(): void
    {
        $woken = $this->wake();

        $this->assertStringContainsString('COMPACTED', $woken);
        $this->assertStringNotContainsString('OPEN WORK', $woken);
        $this->assertStringNotContainsString('PINNED', $woken);
    }

    private function wake(): string
    {
        $io = new RecordingHookIO([
            'hook_event_name' => 'SessionStart',
            'source' => 'compact',
            'session_id' => self::SESSION,
            'transcript_path' => $this->transcriptPath(),
        ], new FakeGit($this->root));

        new CompactionReminder($io)->run([]);

        return $io->emitted === [] ? '' : $io->emitted[0]->context->unwrapOr('');
    }

    private function file(Tag $tag, string $text): void
    {
        Journal::inSession(new Workspace($this->root, self::SESSION))->file(
            new Entry(Kind::Agent, '2026-08-29T14:02:00Z', 'turn', 'msg', Option::some($tag), $text),
        );
    }

    /**
     * @param  array<string, mixed>  ...$records
     */
    private function transcript(array ...$records): void
    {
        file_put_contents(
            $this->transcriptPath(),
            implode('', array_map(fn (array $record) => json_encode($record) . "\n", $records)),
        );
    }

    private function transcriptPath(): string
    {
        return $this->root . '/transcript.jsonl';
    }
}
