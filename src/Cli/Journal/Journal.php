<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
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
     * How many entries a preparation survives before the compaction it was for counts as never having come.
     * A held compaction re-fires within a turn or two of real work, so anything beyond that is a session
     * that carried on — and must be held again rather than sail through on a stale yes.
     */
    private const int PREPARATION_LIFE = 60;

    public function __construct(private readonly StateFile $file) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self(new StateFile($workspace->path('.journal'), self::legend()));
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
                'prepared' => 'yes = the agent has already been sent back once to prepare for the pending '
                    . 'compaction, so the next attempt proceeds instead of being cancelled again',
                'prepared_at' => 'how many entries had been filed when that happened. Work done since means '
                    . 'the compaction never came, so the preparation is spent and the next one is held again',
            ],
            defaults: new State(
                transcript: '',
                session: '',
                chunk: 0,
                prepared: false,
                prepared_at: 0,
            ),
            list: 'one `kind<TAB>time<TAB>turn<TAB>message<TAB>tag<TAB>text` per line, oldest first — the '
                . 'index. `kind` is who spoke, `tag` is what the message said it carried ([!!] pinned, [!] '
                . 'correction, [s]/[e] work started/finished, [d] discovery, …), and `text` is the first line '
                . 'only; the rest lives in the transcript.',
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

        $this->file->write($state->withItems($this->bounded([...$state->items(), $entry->toLine()])));
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

        $this->file->write($state->with(chunk: $state->int('chunk') + 1, prepared: false));
    }

    /**
     * Has the agent already been sent back once to prepare for the compaction now pending? The gate cancels
     * a compaction only on its first attempt; without this the next would be cancelled too and the session
     * could never compact at all. The answer EXPIRES: a cancelled compaction that never returned leaves the
     * agent working on, and a preparation made {@see PREPARATION_LIFE} entries ago was for a compaction that
     * is not this one — so it is spent, and this one is held in its own right.
     */
    public function isPreparedForCompaction(): bool
    {
        $state = $this->file->read();

        return $state->flag('prepared')
            && count($state->items()) - $state->int('prepared_at') < self::PREPARATION_LIFE;
    }

    public function prepare(): void
    {
        $state = $this->file->read();

        $this->file->write($state->with(prepared: true, prepared_at: count($state->items())));
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
     * The facts marked to survive every compaction, oldest first.
     *
     * @return list<Entry>
     */
    public function pinned(): array
    {
        return array_values(array_filter($this->entries(), fn (Entry $entry) => $entry->isPinned()));
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
