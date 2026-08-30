<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\JournalCommand;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\Journal\Reading;
use JesseGall\CodeCommandments\Cli\Journal\Session;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Hooks\Handlers\CompactionGate;
use JesseGall\CodeCommandments\Hooks\Handlers\CompactionReminder;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;
use PHPUnit\Framework\TestCase;

/**
 * A pin that can be STRUCK. `remember` promises a fact will survive every compaction, so it is what an
 * agent reaches for whenever it is afraid of losing something — and the record fills with statements that
 * were true when written and are not now, wearing the same confidence as the ones that still hold. These
 * prove the correction: the newer pin NAMES the one it replaces, the older one is kept and readable, and
 * only the live one is ever carried to somebody who would act on it.
 */
final class SupersededPinTest extends TestCase
{
    private const string SESSION = 'sess-pins';

    private string $root;

    private string|false $priorProjectDir;

    private string|false $priorSessionId;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-pins-' . uniqid('', true);
        mkdir($this->root, 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSessionId = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        // The command resolves its own session; naming it here is what makes the command's journal and
        // the hook's the SAME file, which is the whole point of the pin surviving between them.
        putenv('CLAUDE_CODE_SESSION_ID=' . self::SESSION);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSessionId === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSessionId);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function test_a_struck_pin_stops_being_carried_and_is_never_deleted(): void
    {
        $journal = $this->journal();
        $journal->file(Entry::pin('2026-08-30T01:00:00Z', 'only the FOURTEEN primitives keep one .vue half', Option::none()));
        $journal->file(Entry::pin('2026-08-30T02:00:00Z', 'it is SIXTEEN, counted', Option::some(1)));

        $this->assertSame(
            ['it is SIXTEEN, counted'],
            array_map(fn (Entry $entry) => $entry->text, $journal->pinned()),
            'a corrected fact must not reach a reader who would act on it',
        );

        $this->assertCount(2, $journal->pins(), 'and it must still be in the record');
        $this->assertFalse($journal->pins()[0]->isLive());
        $this->assertSame(2, $journal->pins()[0]->supersededBy->unwrap());
        $this->assertSame('only the FOURTEEN primitives keep one .vue half', $journal->pins()[0]->text());
    }

    /**
     * The number is what a reader types to strike one, so striking may not renumber the rest — a listing
     * read a minute ago has to still mean what it said.
     */
    public function test_the_numbers_are_stable_across_a_strike(): void
    {
        $journal = $this->journal();
        $journal->file(Entry::pin('2026-08-30T01:00:00Z', 'first', Option::none()));
        $journal->file(Entry::pin('2026-08-30T02:00:00Z', 'second', Option::none()));
        $journal->file(Entry::pin('2026-08-30T03:00:00Z', 'second, corrected', Option::some(2)));

        $this->assertSame([1, 2, 3], array_map(fn ($pin) => $pin->number, $journal->pins()));
        $this->assertSame('first', $journal->pin(1)->unwrap()->text());
        $this->assertTrue($journal->pin(4)->isNone());
    }

    /**
     * The line format grows in place. A journal written before the field existed still reads back — it
     * holds the open work no summary can reconstruct, so an upgrade that dropped it would be the very
     * loss the journal exists to prevent.
     */
    public function test_a_line_written_before_the_field_existed_still_reads(): void
    {
        $legacy = implode("\t", ['agent', '2026-08-29T14:02:00Z', 'turn', 'msg', '!pinned', 'motion.ts is FORBIDDEN']);
        $entry = Entry::fromLine($legacy)->unwrap();

        $this->assertSame('motion.ts is FORBIDDEN', $entry->text);
        $this->assertTrue($entry->supersedes()->isNone());
        $this->assertTrue($entry->isPinned());
    }

    public function test_the_reference_survives_the_round_trip(): void
    {
        $pin = Entry::pin('2026-08-30T02:00:00Z', 'it is SIXTEEN', Option::some(7));
        $back = Entry::fromLine($pin->toLine())->unwrap();

        $this->assertSame('it is SIXTEEN', $back->text);
        $this->assertSame(7, $back->supersedes()->unwrap());
        $this->assertSame(Tag::Pinned, $back->tag->unwrap());
    }

    /**
     * Striking has to be one command, or nobody does it and the mechanism is theatre.
     */
    public function test_one_command_pins_the_correction_and_strikes_what_it_replaces(): void
    {
        $this->assertSame(0, $this->exitOf('remember', 'the FOURTEEN primitives keep one .vue half'));
        $said = $this->saidBy('remember', 'it is SIXTEEN', '--supersedes=1');

        $this->assertStringContainsString('superseding pin 1', $said);
        $this->assertStringContainsString('kept', $said);
        $this->assertSame(['it is SIXTEEN'], array_map(fn (Entry $entry) => $entry->text, $this->journal()->pinned()));
    }

    /**
     * A number that names no pin is a reader working from another session or from memory, and filing the
     * correction anyway would leave it standing beside the fact it was meant to replace.
     */
    public function test_striking_a_pin_that_does_not_exist_is_refused(): void
    {
        $this->assertSame(Console::REFUSED, $this->exitOf('remember', 'a fact', '--supersedes=9'));
        $this->assertSame([], $this->journal()->pins(), 'a refused strike files nothing');
    }

    public function test_striking_a_pin_that_was_already_struck_names_the_one_that_stands(): void
    {
        $this->exitOf('remember', 'first');
        $this->exitOf('remember', 'second', '--supersedes=1');

        $said = $this->saidBy('remember', 'third', '--supersedes=1');

        $this->assertStringContainsString('already superseded by pin 2', $said);
        $this->assertCount(2, $this->journal()->pins());
    }

    public function test_an_empty_fact_is_refused_rather_than_filed(): void
    {
        $this->assertSame(Console::REFUSED, $this->exitOf('remember'));
    }

    /**
     * The listing is where the numbers come from, so it is the one view a struck pin still appears in.
     */
    public function test_the_listing_shows_the_struck_pin_and_what_replaced_it(): void
    {
        $this->exitOf('remember', 'the FOURTEEN primitives keep one .vue half');
        $this->exitOf('remember', 'it is SIXTEEN', '--supersedes=1');

        $listed = new Reading(new Session(self::SESSION, $this->root . '/transcript.jsonl', 0, ''), $this->root)->pinned();

        $this->assertStringContainsString('FOURTEEN', $listed);
        $this->assertStringContainsString('superseded by pin 2', $listed);
        $this->assertStringContainsString('(supersedes pin 1)', $listed);
        $this->assertStringContainsString('(2, 1 still standing)', $listed);
    }

    /**
     * The compacted reader is on a measured byte budget, and a fact that has been corrected may not spend
     * it — nor be read as current by an agent that has just lost the conversation.
     */
    public function test_a_compacted_reader_wakes_to_the_correction_and_not_the_stale_fact(): void
    {
        $journal = $this->journal();
        $journal->file(Entry::pin('2026-08-30T01:00:00Z', 'FOURTEEN primitives keep one .vue half', Option::none()));
        $journal->file(Entry::pin('2026-08-30T02:00:00Z', 'SIXTEEN primitives keep one .vue half', Option::some(1)));

        $io = new RecordingHookIO([
            'hook_event_name' => 'SessionStart',
            'source' => 'compact',
            'session_id' => self::SESSION,
            'transcript_path' => $this->root . '/transcript.jsonl',
        ], new FakeGit($this->root));

        new CompactionReminder($io)->run([]);
        $woken = $io->emitted[0]->context->unwrapOr('');

        $this->assertStringContainsString('SIXTEEN', $woken);
        $this->assertStringNotContainsString('FOURTEEN', $woken);
        $this->assertStringContainsString('02:00', $woken, 'a pin says when it was measured');
    }

    /**
     * And the summariser is told to keep the fact that stands, not the one it replaced — the instructions
     * are the other half of the same promise.
     */
    public function test_the_summariser_is_told_to_keep_only_the_fact_that_stands(): void
    {
        $journal = $this->journal();
        $journal->file(Entry::pin('2026-08-30T01:00:00Z', 'IN FLIGHT: two workers, uncommitted', Option::none()));
        $journal->file(Entry::pin('2026-08-30T02:00:00Z', 'both workers landed at v4.294.0', Option::some(1)));

        $io = new RecordingHookIO([
            'hook_event_name' => 'PreCompact',
            'session_id' => self::SESSION,
            'transcript_path' => $this->root . '/transcript.jsonl',
        ], new FakeGit($this->root));

        new CompactionGate($io)->run([]);
        $instructions = $io->emitted[0]->json('PreCompact');

        $this->assertStringContainsString('both workers landed', $instructions);
        $this->assertStringNotContainsString('IN FLIGHT', $instructions);
    }

    /**
     * A pinned entry the RECORDER files — an agent that typed `[!pinned]` in a message — supersedes
     * nothing and says so by saying nothing, so it constructs exactly as it always did.
     */
    public function test_an_ordinary_entry_needs_no_reference(): void
    {
        $entry = new Entry(Kind::Agent, 'now', 't', 'm', Tag::parse('[!pinned] a fact'), '[!pinned] a fact');

        $this->assertTrue($entry->supersedes()->isNone());
        $this->assertTrue(Entry::fromLine($entry->toLine())->unwrap()->supersedes()->isNone());
    }

    /**
     * A pin past the cap is not the pin the confirmation promises. A compaction carries only the newest
     * {@see Journal::CARRIED}, so the thirteenth does not add to the record — it displaces the first — and
     * the moment to say so is the one moment the agent is thinking about pins at all.
     */
    public function test_a_pin_past_the_cap_names_what_it_displaced(): void
    {
        $journal = $this->journal();

        foreach (range(1, Journal::CARRIED - 1) as $n) {
            $journal->file(Entry::pin(sprintf('2026-08-30T00:00:%02dZ', $n), "fact {$n}", Option::none()));
        }

        $atTheCap = $this->saidBy('remember', 'the twelfth fact');

        $this->assertStringNotContainsString('no longer reaches one', $atTheCap, 'every pin still reaches a compaction, so there is nothing to warn about');

        $past = $this->saidBy('remember', 'the thirteenth fact');

        $this->assertStringContainsString('13 pins stand', $past, 'it says the number it counted');
        $this->assertStringContainsString('only the newest 12', $past);
        $this->assertStringContainsString('fact 1', $past, 'it names the pin that was displaced, not merely a count');
        $this->assertStringContainsString('--supersedes', $past, 'and the one command that frees a slot');
    }

    private function journal(): Journal
    {
        return Journal::inSession(Workspace::ofSession($this->root));
    }

    /**
     * The command, run as a person would — its exit code.
     */
    private function exitOf(string ...$args): int
    {
        $out = fopen('php://memory', 'r+');

        return new JournalCommand(
            new RecordingHookIO([], new FakeGit($this->root)),
            new Console($out),
        )->run(Input::of('journal', $args));
    }

    /**
     * What the command said.
     */
    private function saidBy(string ...$args): string
    {
        $out = fopen('php://memory', 'r+');

        new JournalCommand(new RecordingHookIO([], new FakeGit($this->root)), new Console($out))
            ->run(Input::of('journal', $args));

        rewind($out);

        return (string) stream_get_contents($out);
    }
}
