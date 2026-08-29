<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\PhpTypes\Option;

/**
 * Files the conversation into the session {@see Journal} as it happens, so a compaction cannot take it:
 * the user's words at `UserPromptSubmit`, the agent's at `MessageDisplay` (the one moment a hook sees
 * what the agent itself is saying), the boundary at `PostCompact`, and the transcript these entries index
 * at `SessionStart`. It writes and never speaks — an index is only worth keeping if it costs the session
 * nothing to keep.
 */
final class JournalRecorder extends Hook
{
    public function summary(): string
    {
        return 'Files each message, its tag and every compaction boundary into the session journal, so `commandments journal` can rebuild what a compaction dropped.';
    }

    public function bindings(): array
    {
        return [
            new HookBinding('MessageDisplay'),
            new HookBinding('UserPromptSubmit'),
            new HookBinding('PostCompact'),
            new HookBinding('SessionStart'),
        ];
    }

    /**
     * A message arrives in flushes, each delta holding only the lines completed since the last one — so the
     * FIRST flush is the one carrying the message's opening, which is both its tag and the line the journal
     * files. Every later flush of the same message is the middle of a sentence, and is ignored.
     */
    protected function onMessageDisplay(HookEvent $event): int
    {
        if (! $event->isFirstFlush()) {
            return $this->pass();
        }

        return $this->record($event, Kind::Agent, $event->delta());
    }

    /**
     * The user's words are filed WHOLE and never read for tags. The vocabulary is the agent's discipline,
     * not theirs — and a user who pastes a transcript, quotes the brief, or asks about a tag would
     * otherwise open work nobody started, which the stop gate then holds for and the write gate then
     * treats as permission to change files undeclared.
     */
    protected function onUserPromptSubmit(HookEvent $event): int
    {
        if (trim($event->prompt()) === '') {
            return $this->pass();
        }

        Journal::inSession($event->sessionWorkspace())->file($this->entry($event, Kind::User, Option::none(), $event->prompt()));

        return $this->pass();
    }

    protected function onPostCompact(HookEvent $event): int
    {
        Journal::inSession($event->sessionWorkspace())->markCompaction($this->now(), $event->compactSummary());

        return $this->pass();
    }

    /**
     * Which transcript this session's entries index. Recorded at every start — including `compact` and
     * `resume`, which continue a live session — because that is the one moment the path is known before
     * anything needs to read it.
     */
    protected function onSessionStart(HookEvent $event): int
    {
        Journal::inSession($event->sessionWorkspace())->follow($event->transcriptPath(), $event->sessionId());

        return $this->pass();
    }

    /**
     * File what $text carried. A tag opens a LINE, so a message that closes one piece of work and opens
     * the next files both — one entry per tagged line, and a single untagged entry for a message that
     * declared nothing.
     */
    private function record(HookEvent $event, Kind $kind, string $text): int
    {
        if (trim($text) === '') {
            return $this->pass();
        }

        $journal = Journal::inSession($event->sessionWorkspace());
        $tagged = Tag::taggedLines($text);

        if ($tagged === []) {
            $journal->file($this->entry($event, $kind, Option::none(), $text));

            return $this->pass();
        }

        foreach ($tagged as [$tag, $line]) {
            $journal->file($this->entry($event, $kind, Option::some($tag), $line));
        }

        return $this->pass();
    }

    private function entry(HookEvent $event, Kind $kind, Option $tag, string $text): Entry
    {
        return new Entry($kind, $this->now(), $event->turnId(), $event->messageId(), $tag, $text);
    }

    private function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
