<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Journal;

use Generator;
use JesseGall\PhpTypes\Option;

/**
 * Reads a session's `.jsonl` transcript — the complete, lossless record of a conversation, which the
 * journal indexes rather than copies. It is read a line at a time and categorised by the FIELDS each line
 * carries, never by what its text looks like: a real session's 1467 `user` lines are 38 people speaking,
 * 1372 tool results and the rest synthesized, and only the fields tell them apart.
 */
final class Transcript
{
    /**
     * `type`s that are the harness's own bookkeeping beside the conversation, carrying no message at all.
     */
    private const array BOOKKEEPING = [
        'summary', 'last-prompt', 'ai-title', 'agent-name', 'mode', 'permission-mode',
        'file-history-snapshot', 'file-history-delta', 'queue-operation', 'pr-link',
    ];

    /**
     * `promptSource`s that mean a HUMAN put the words there — typed at the prompt, or queued to be. The
     * others (`system`, `sdk`) are the loop speaking in the user's turn.
     */
    private const array HUMAN = ['typed', 'queued'];

    /**
     * The `system` subtype a compaction writes where it happened — the chunk divider, in the record itself.
     */
    private const string BOUNDARY = 'compact_boundary';

    public function __construct(private readonly string $path) {}

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Every line of the transcript, in order. A generator, because a long session's transcript runs to
     * tens of megabytes and no reader needs it all in memory at once.
     *
     * @return Generator<int, Line>
     */
    public function lines(): Generator
    {
        $handle = @fopen($this->path, 'r');

        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            foreach (Record::decode($line) as $record) {
                yield new Line($this->categorise($record), $record->at()->unwrapOr(''), $record->said());
            }
        }

        fclose($handle);
    }

    /**
     * The lines of one chunk — the stretch between two compaction boundaries. `$back` counts backwards
     * from the conversation as it stands: 0 is since the last compaction, 1 the chunk before it.
     *
     * @return list<Line>
     */
    public function chunk(int $back = 0): array
    {
        $chunks = [[]];

        foreach ($this->lines() as $line) {
            if ($line->category === Category::Boundary) {
                $chunks[] = [];

                continue;
            }

            $chunks[count($chunks) - 1][] = $line;
        }

        return $chunks[count($chunks) - 1 - max(0, $back)] ?? [];
    }

    /**
     * How many compactions this transcript has been through. The boundary is written INTO the file, so
     * this is answered by the record itself rather than by anything a hook had to be present for.
     */
    public function compactions(): int
    {
        $count = 0;

        foreach ($this->lines() as $line) {
            if ($line->category === Category::Boundary) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * What a line IS, decided by the fields it carries.
     */
    private function categorise(Record $record): Category
    {
        $type = $record->type();

        if (in_array($type, self::BOOKKEEPING, true)) {
            return Category::Bookkeeping;
        }

        if ($type === 'system') {
            return $record->subtype() === self::BOUNDARY ? Category::Boundary : Category::Injected;
        }

        if ($type === 'attachment') {
            return Category::Injected;
        }

        if ($type === 'assistant') {
            return Category::Reply;
        }

        return $type === 'user' ? $this->categoriseUser($record) : Category::Bookkeeping;
    }

    /**
     * Which of the four things written under `type: "user"` this is. Only one of them is a person.
     */
    private function categoriseUser(Record $record): Category
    {
        if ($record->isCompactSummary()) {
            return Category::Summary;
        }

        if ($record->isToolResult()) {
            return Category::ToolResult;
        }

        if ($record->isSynthesized()) {
            return Category::Injected;
        }

        return in_array($record->promptSource(), self::HUMAN, true) ? Category::Prompt : Category::Injected;
    }
}
