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

    protected function onUserPromptSubmit(HookEvent $event): int
    {
        return $this->record($event, Kind::User, $event->prompt());
    }

    protected function onPostCompact(HookEvent $event): int
    {
        Journal::inSession($event->workspace())->markCompaction($this->now(), $event->compactSummary());

        return $this->pass();
    }

    /**
     * Which transcript this session's entries index. Recorded at every start — including `compact` and
     * `resume`, which continue a live session — because that is the one moment the path is known before
     * anything needs to read it.
     */
    protected function onSessionStart(HookEvent $event): int
    {
        Journal::inSession($event->workspace())->follow($event->transcriptPath(), $event->sessionId());

        return $this->pass();
    }

    private function record(HookEvent $event, Kind $kind, string $text): int
    {
        if (trim($text) === '') {
            return $this->pass();
        }

        Journal::inSession($event->workspace())->file(new Entry(
            $kind,
            $this->now(),
            $event->turnId(),
            $event->messageId(),
            Tag::parse($text),
            $text,
        ));

        return $this->pass();
    }

    private function now(): string
    {
        return gmdate('Y-m-d\TH:i:s\Z');
    }
}
