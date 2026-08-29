<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\TouchedSources;
use JesseGall\CodeCommandments\Support\Binary;

/**
 * Nothing is changed until the agent has SAID what it is changing. Unfinished work is the one thing a
 * compaction cannot reconstruct, and it only exists to be carried across if the work was declared when it
 * began — so a tool that names the file it will write is refused outright while no `[!start]` stands, and a
 * shell command, which cannot be judged before it runs, is caught the moment it has written one
 * ({@see TouchedSources} asks the TREE rather than reading the command).
 */
final class WriteGate extends Hook
{
    /**
     * The tools that say which file they will write, so their intent is known before they run.
     */
    private const array NAMED_WRITERS = ['Edit', 'Write', 'MultiEdit', 'NotebookEdit'];

    /**
     * How many changed files a refusal names back.
     */
    private const int SHOWN = 5;

    /**
     * Agent messages the journal must already hold before this gate enforces anything. Without a
     * `MessageDisplay` hook there are no spans to open, and a gate that cannot be satisfied would refuse
     * every write for ever — so it stays silent until the recorder has demonstrably worked.
     */
    private const int PROOF = 3;

    public function summary(): string
    {
        return 'Refuses a file-changing tool while no `[!start]` stands, so work is always declared before it happens and unfinished work survives a compaction.';
    }

    public function bindings(): array
    {
        return [
            ...array_map(fn (string $tool) => new HookBinding('PreToolUse', $tool), self::NAMED_WRITERS),
            new HookBinding('PostToolUse', 'Bash'),
        ];
    }

    /**
     * A tool that names its file is stopped BEFORE it writes — the only refusal that costs nothing, since
     * nothing has happened yet.
     */
    protected function onPreToolUse(HookEvent $event): int
    {
        if (! in_array($event->tool(), self::NAMED_WRITERS, true)) {
            return $this->pass();
        }

        $journal = Journal::inSession($event->workspace());

        if (! $this->isEnforceable($journal)) {
            return $this->pass();
        }

        if ($journal->openSpans() !== []) {
            return $this->pass();
        }

        return $this->block($this->refusal($event, "You are about to change {$event->filePath()} with no work declared."));
    }

    /**
     * A shell command cannot be judged before it runs without reading it, and reading a command to guess
     * what it will write is how a parser starts lying. So it is judged AFTER, by what actually changed.
     */
    protected function onPostToolUse(HookEvent $event): int
    {
        if (! $event->isTool('Bash')) {
            return $this->pass();
        }

        $journal = Journal::inSession($event->workspace());
        $touched = new TouchedSources($event->workspace(), $event->root, Config::load($event->root), 'writes');
        $changed = $touched->claim(self::SHOWN);

        if ($changed === [] || ! $this->isEnforceable($journal) || $journal->openSpans() !== []) {
            return $this->pass();
        }

        $files = implode(', ', array_map(fn (string $path) => basename($path), $changed));

        return $this->block($this->refusal($event, "That shell command changed {$files} with no work declared."));
    }

    /**
     * Can this gate be satisfied at all? It enforces only once the journal holds enough of the agent's own
     * messages to prove they are being recorded — otherwise a session whose recorder never fired would be
     * refused every write with no way to answer.
     */
    private function isEnforceable(Journal $journal): bool
    {
        $recorded = 0;

        foreach ($journal->entries() as $entry) {
            if ($entry->kind === Kind::Agent) {
                $recorded++;
            }
        }

        return $recorded >= self::PROOF;
    }

    private function refusal(HookEvent $event, string $what): string
    {
        $binary = Binary::in($event->root);
        $start = Tag::Start->marker();
        $end = Tag::End->marker();

        return <<<TEXT
            Code Commandments — {$what}

            Say what you are starting, in a message of your own, before you change anything:

              {$start} <the piece of work, in a few words>

            and close it when it is done with {$end} and the same words. The pair is what makes work in
            flight visible on the far side of a compaction — a start with no end is the first thing the
            next reader needs, and it can only exist if you declared the work when you began it.

            `{$binary} journal instructions` is the whole brief.
            TEXT;
    }
}
