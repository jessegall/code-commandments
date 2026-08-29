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
                'previous_session' => 'the session this one continued from, so a reader can walk further back',
                'chunk' => 'how many compactions have happened — entry chunks are numbered from 0',
                'prepared' => 'yes = the agent has already been sent back once to prepare for the pending '
                    . 'compaction, so the next attempt proceeds instead of being cancelled again',
            ],
            defaults: new State(
                transcript: '',
                session: '',
                previous_session: '',
                chunk: 0,
                prepared: false,
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
     * File $entry. The oldest are dropped once {@see CAPACITY} is reached, so the file a per-flush hook
     * writes stays bounded.
     */
    public function file(Entry $entry): void
    {
        $state = $this->file->read();
        $items = [...$state->items(), $entry->toLine()];

        $this->file->write($state->withItems(array_slice($items, -self::CAPACITY)));
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
     * Record which transcript and session these entries belong to, and what this session continued from.
     * The chain is what lets a reader walk back past a session boundary into an earlier transcript.
     */
    public function follow(string $transcript, string $session, string $previous = ''): void
    {
        $state = $this->file->read();

        $this->file->write($state->with(
            transcript: $transcript,
            session: $session,
            previous_session: $previous === '' ? $state->text('previous_session') : $previous,
        ));
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
     * Has the agent already been sent back once to prepare for the compaction now pending? The gate
     * cancels a compaction only on the first attempt; without this the next attempt would be cancelled
     * too, and the session could never compact at all.
     */
    public function isPreparedForCompaction(): bool
    {
        return $this->file->read()->flag('prepared');
    }

    public function prepare(): void
    {
        $this->file->write($this->file->read()->with(prepared: true));
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
