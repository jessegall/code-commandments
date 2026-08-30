<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Agent;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * The session's INDEX over its own transcript. A compaction rewrites the conversation into a summary
 * that keeps what was done and loses what was decided; the transcript on disk still holds every word, so
 * this records only what the transcript cannot answer — where the compaction boundaries fall, how the
 * sessions chain, and what each message said it carried ({@see Tag}) — and the text is read from the
 * transcript live ({@see Transcript}). One file, so lifting it drops the whole index at once.
 */
final class Journal
{
    /**
     * Entries kept before the oldest are dropped. Generous enough to span several compactions of a long
     * session, bounded so a file a hook appends to on every message flush cannot grow without end.
     */
    private const int CAPACITY = 4000;

    /**
     * What the index is called inside a session's folder. Stated once, so anything that has to
     * RECOGNISE a journal among a folder's other files — {@see \JesseGall\CodeCommandments\Cli\State\Adoption}
     * merging a stranded folder in — names the same file the writers do.
     */
    public const string FILE = '.journal';

    public function __construct(private readonly StateFile $file) {}

    public static function inSession(Workspace $workspace): self
    {
        return self::at($workspace->path(self::FILE));
    }

    /**
     * The journal kept at $path — how a journal that belongs to no live workspace is read, which is what
     * a folder being adopted holds.
     */
    public static function at(string $path): self
    {
        return new self(new StateFile($path, self::legend()));
    }

    /**
     * A WORKER's own journal, beside the session's rather than inside it. A one-shot worker's record
     * helps only somebody else, but an agent kept alive across dispatches has the same compaction
     * problem the orchestrator has — and this is what it reads back.
     */
    public static function ofAgent(Workspace $workspace, Agent $agent): self
    {
        return self::at($workspace->agentPath($agent, self::FILE));
    }

    public static function legend(): Legend
    {
        return new Legend(
            'The conversation index for code-commandments (`commandments journal`). It is not a copy of the '
                . 'conversation — the session transcript is that, and this points into it. After a compaction, '
                . '`commandments journal` reads this to rebuild what the summary dropped.',
            [
                'transcript' => "the session transcript this indexes — the `.jsonl` holding every word",
                'session' => 'the Claude Code session id these entries belong to',
                'chunk' => 'how many compactions have happened — entry chunks are numbered from 0',
                'quiet_calls' => 'tool calls since anything was TAGGED or pinned. It is what the nudge '
                    . 'measures, so the nudge counts silence rather than time — and it resets when '
                    . 'something is recorded, never when the nudge fires, or it becomes a metronome',
                'calls' => 'tool calls this session, counted for good. It is the WORK measure a nudge '
                    . 'paces itself against — entries will not do, because a message is itself an entry, '
                    . 'so pacing on those fires every time the agent speaks',
            ],
            defaults: new State(
                transcript: '',
                session: '',
                chunk: 0,
                quiet_calls: 0,
                calls: 0,
            ),
            list: 'one `kind<TAB>time<TAB>turn<TAB>message<TAB>tag<TAB>[>pin<TAB>]text` per line, oldest '
                . 'first — the index. `kind` is who spoke, `tag` is what the message said it carried ([!!] '
                . 'pinned, [!] correction, [s]/[e] work started/finished, [d] discovery, …), and `text` is the '
                . 'first line only; the rest lives in the transcript. A pinned line that CORRECTS an earlier '
                . 'one carries `>N` before its text — the pin it supersedes, counted from 1 among the pinned '
                . 'lines. Nothing is ever struck out of this file: the superseded line stays where it is, and '
                . 'only stops being carried to a reader who would take it as current.',
            safe: 'the transcript still holds the conversation — only the compaction boundaries are lost',
        );
    }

    /**
     * Every entry filed, oldest first.
     *
     * @return list<Entry>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ($this->file->read()->items() as $line) {
            foreach (Entry::fromLine($line) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * File $entry, dropping the oldest once {@see CAPACITY} is reached so the index stays bounded. A
     * PINNED entry is never dropped: it was marked precisely because it must outlive the conversation
     * around it, and a long session would otherwise age out the very facts it was told to keep.
     */
    public function file(Entry $entry): void
    {
        $state = $this->file->read();
        $recorded = $state->withItems($this->bounded([...$state->items(), $entry->toLine()]));

        // A TAGGED line is the thing the nudge is asking for, so filing one clears the debt. Untagged
        // narration is not — it is what the silence is made of.
        $this->file->write($entry->tag->isSome() ? $recorded->with(quiet_calls: 0) : $recorded);
    }

    /**
     * Count one tool call against the silence, and answer how long it has been. The nudge measures what
     * has NOT been said rather than how much time has passed — a stretch of reading and dispatching says
     * nothing and is exactly when a ruling goes unrecorded.
     *
     * Counting is its OWN verb, kept apart from the question {@see quietFor} asks: a getter that counts
     * answers a different number to its second caller, and a nudge naming that number names one nothing
     * measured.
     */
    public function countCall(): int
    {
        $state = $this->file->read();
        $quiet = $state->int('quiet_calls') + 1;

        $this->file->write($state->with(quiet_calls: $quiet, calls: $state->int('calls') + 1));

        return $quiet;
    }

    /**
     * How long the silence has run, asked without adding to it — so a reader deciding whether to speak
     * cannot change the answer by looking.
     */
    public function quietFor(): int
    {
        return $this->file->read()->int('quiet_calls');
    }

    /**
     * How much WORK this session has done. Tool calls rather than entries, because a message is itself an
     * entry: pacing a nudge on entries fires it every time the agent speaks, which is every stop.
     */
    public function calls(): int
    {
        return $this->file->read()->int('calls');
    }

    /**
     * How many lines in this stretch carried a tag — the number the nudge says back, because "3 tags in
     * 40 tool calls" is a fact to act on where "remember to tag" is wallpaper.
     */
    public function tagged(): int
    {
        $tagged = 0;

        foreach ($this->entries() as $entry) {
            $tagged += $entry->tag->isSome() ? 1 : 0;
        }

        return $tagged;
    }

    /**
     * Take $other's index into this one, INTERLEAVED by the moment each line was filed. A stranded folder
     * holds an earlier stretch of the same conversation, so appending it as a block would put the opening
     * of the session after its middle — and the order is the only thing that makes two stretches readable
     * together. A line already filed here is not filed twice, so absorbing the same folder again is a
     * no-op rather than a doubled record.
     *
     * The work counts SUM, because the two stretches are disjoint halves of one session's work; the
     * silence count and the transcript stay this journal's own, since those describe where the session is
     * NOW — except a transcript this side never learned, which the other side can still supply.
     */
    public function absorb(self $other): void
    {
        $taken = $other->file->read();
        $mine = $this->file->read();

        $this->file->write($mine
            ->withItems($this->bounded(self::interleaved($mine->items(), $taken->items())))
            ->with(
                transcript: $mine->text('transcript') ?: $taken->text('transcript'),
                session: $mine->text('session') ?: $taken->text('session'),
                chunk: max($mine->int('chunk'), $taken->int('chunk')),
                calls: $mine->int('calls') + $taken->int('calls'),
            ));
    }

    /**
     * $mine and $theirs as one index, ordered by the stamp each line carries and de-duplicated. A line
     * this format did not write sorts by an empty stamp — first, where a hand-edited note is still read
     * rather than dropped.
     *
     * @param  list<string>  $mine
     * @param  list<string>  $theirs
     * @return list<string>
     */
    private static function interleaved(array $mine, array $theirs): array
    {
        $lines = array_values(array_unique([...$mine, ...$theirs]));
        $stamps = array_map(static fn (string $line) => Entry::fromLine($line)->mapOr('', fn (Entry $entry) => $entry->at), $lines);

        // A stable sort, so two lines filed in the same second keep the order they were written in.
        array_multisort($stamps, SORT_ASC, SORT_STRING, $lines);

        return $lines;
    }

    /**
     * $lines cut to {@see CAPACITY}, oldest first out, keeping every pinned line whatever its age — their
     * room is taken out of the budget before anything else, so the bound holds rather than growing by one
     * for every fact that was pinned.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function bounded(array $lines): array
    {
        if (count($lines) <= self::CAPACITY) {
            return $lines;
        }

        $budget = max(0, self::CAPACITY - count(array_filter($lines, $this->isPinnedLine(...))));
        $kept = [];

        foreach (array_reverse($lines, preserve_keys: true) as $at => $line) {
            if ($this->isPinnedLine($line)) {
                $kept[$at] = $line;

                continue;
            }

            if ($budget <= 0) {
                continue;
            }

            $kept[$at] = $line;
            $budget--;
        }

        ksort($kept);

        return array_values($kept);
    }

    /**
     * Was $line filed as a fact that must outlive the conversation around it?
     */
    private function isPinnedLine(string $line): bool
    {
        return Entry::fromLine($line)->isSomeAnd(fn (Entry $entry) => $entry->isPinned());
    }

    /**
     * The transcript these entries index, absent until a hook has seen one.
     *
     * @return Option<string>
     */
    public function transcript(): Option
    {
        return Option::fromTruthy($this->file->read()->text('transcript'));
    }

    /**
     * Record which transcript and session these entries belong to. Walking further back is
     * {@see Sessions}'s job and it reads the transcripts themselves, so no chain is kept here.
     */
    public function follow(string $transcript, string $session): void
    {
        $this->file->write($this->file->read()->with(transcript: $transcript, session: $session));
    }

    /**
     * How many compactions this session has been through. Entries are grouped into chunks by the marks
     * between them, so `--back=1` is the chunk before the current one.
     */
    public function chunk(): int
    {
        return $this->file->read()->int('chunk');
    }

    /**
     * Record that a compaction happened: the boundary is filed as a mark and the chunk counter moves on,
     * with the summary it produced kept beside it so a reader can see what was CLAIMED to survive.
     */
    public function markCompaction(string $at, string $summary): void
    {
        $this->file(new Entry(Kind::Mark, $at, '', '', Option::none(), 'compacted: ' . $summary));

        $state = $this->file->read();

        $this->file->write($state->with(chunk: $state->int('chunk') + 1));
    }

    /**
     * Has the recorder demonstrably worked — are at least $atLeast of the agent's own messages filed? A
     * session where it never fired has no way to open a span, so a gate that enforced there would refuse
     * every write for ever with no answer available.
     */
    public function hasRecorded(int $atLeast): bool
    {
        $recorded = 0;

        foreach ($this->entries() as $entry) {
            if ($entry->kind === Kind::Agent) {
                $recorded++;
            }
        }

        return $recorded >= $atLeast;
    }

    /**
     * The facts marked to survive every compaction that STILL STAND, oldest first — a pin a later one
     * superseded is not one of them. Nothing is deleted to make that true: the struck pin is still in the
     * file and still listed ({@see pins}); it simply stops being carried forward, because every place this
     * feeds is somewhere a reader takes what it says as current.
     *
     * @return list<Entry>
     */
    public function pinned(): array
    {
        $live = [];

        foreach ($this->pins() as $pin) {
            if ($pin->isLive()) {
                $live[] = $pin->entry;
            }
        }

        return $live;
    }

    /**
     * Every pinned fact, struck ones included, numbered as {@see Pin} counts them — what a reader is shown
     * when they are choosing which one to correct, and the only view in which a superseded fact still
     * appears.
     *
     * @return list<Pin>
     */
    public function pins(): array
    {
        return Pin::numbered(array_values(array_filter($this->entries(), fn (Entry $entry) => $entry->isPinned())));
    }

    /**
     * The pin numbered $number, absent when the session has no such pin — which is what a reader who typed
     * a number from another session, or from memory, has done.
     *
     * @return Option<Pin>
     */
    public function pin(int $number): Option
    {
        foreach ($this->pins() as $pin) {
            if ($pin->number === $number) {
                return Option::some($pin);
            }
        }

        return Option::none();
    }

    /**
     * The work started and not yet finished — every {@see Tag::Start} with no {@see Tag::End} after it.
     * This is the one thing in the journal that is live state rather than history, so it is what a
     * compaction must carry across and what a stop must account for.
     *
     * @return list<Entry>
     */
    public function openSpans(): array
    {
        $open = [];

        foreach ($this->entries() as $entry) {
            foreach ($entry->tag as $tag) {
                if ($tag->isSpanOpener()) {
                    $open[] = $entry;
                }

                if ($tag->isSpanCloser()) {
                    array_pop($open);
                }
            }
        }

        return $open;
    }

    public function exists(): bool
    {
        return $this->file->exists();
    }

    public function path(): string
    {
        return $this->file->path();
    }
}
