<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Session;

use Generator;

/**
 * Reads a session's `.jsonl` transcript — the record the harness writes — for the one thing this package
 * asks of it: what the session is CALLED, so `session list` can say what a folder named after a hash
 * holds. It is read a line at a time and categorised by the FIELDS each line carries, never by what its
 * text looks like: a real session's 1467 `user` lines are 38 people speaking, 1372 tool results and the
 * rest synthesized, and only the fields tell them apart.
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
     * Every decoded line of the transcript, in order. A generator, because a long session's transcript
     * runs to tens of megabytes and no reader needs it all in memory at once.
     *
     * @return Generator<int, Record>
     */
    public function records(): Generator
    {
        if (! $this->exists()) {
            return; // An event can arrive with no transcript at all, and an empty path is a ValueError rather than a false.
        }

        $handle = @fopen($this->path, 'r');

        if ($handle === false) {
            return;
        }

        while (($line = fgets($handle)) !== false) {
            foreach (Record::decode($line) as $record) {
                yield $record;
            }
        }

        fclose($handle);
    }

    /**
     * How this session is known to a human — the title the harness generated for it, else the first thing
     * the user actually said. Only the head of the file is read: both appear early, and a menu of sessions
     * must not cost a full pass over every transcript in the project.
     */
    public function name(int $within = 600): string
    {
        $spoken = '';
        $read = 0;

        foreach ($this->records() as $record) {
            if ($record->title() !== '') {
                return $record->title();
            }

            if ($spoken === '' && $this->categorise($record) === Category::Prompt) {
                $spoken = $record->said();
            }

            if (++$read >= $within) {
                break;
            }
        }

        return $spoken;
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
