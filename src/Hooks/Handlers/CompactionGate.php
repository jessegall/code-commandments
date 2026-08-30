<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

/**
 * Writes a compaction its own instructions from the {@see Journal} — the facts and the unfinished work the
 * summary may not drop — on EVERY compaction, asked-for or automatic, since a summary the user requested
 * loses as much as one the context forced. It never cancels one: blocking does not defer a compaction, so
 * the turn it buys is paid for in the very resource that has run out. Pinning happens as the work does,
 * which is {@see CompactionReminder}'s job.
 */
final class CompactionGate extends Hook
{
    public function summary(): string
    {
        return 'Writes a compaction its own instructions from the journal, naming the facts and the unfinished work the summary may not drop.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PreCompact')];
    }

    protected function onPreCompact(HookEvent $event): int
    {
        return $this->instruct($this->instructions(Journal::inSession($event->sessionWorkspace())));
    }

    /**
     * The instructions the compaction is summarised under. The harness takes a `PreCompact` hook's stdout
     * verbatim as its `newCustomInstructions`, so this is the one moment anything can tell the summariser
     * what it is not allowed to drop.
     */
    private function instructions(Journal $journal): string
    {
        $pinned = $this->listing('These facts MUST appear in the summary, verbatim:', $journal->pinned());
        $open = $this->listing('This work is IN FLIGHT and unfinished — the summary must carry it:', $journal->openSpans());

        return <<<TEXT
            PRESERVE DECISIONS, NOT JUST ACTIONS. A summary of this conversation is worthless if it records what
            was done and loses what was decided. Keep, in the user's own words wherever they said it: every
            ruling and correction they gave, every approach that was rejected and why, every constraint stated
            once, and anything they had to repeat. Prefer their words to a paraphrase.
            {$pinned}{$open}
            TEXT;
    }

    /**
     * A heading over the entries beneath it, or nothing at all when there are none.
     *
     * @param  list<Entry>  $entries
     */
    private function listing(string $heading, array $entries): string
    {
        if ($entries === []) {
            return '';
        }

        $bullets = implode("\n", array_map(fn (Entry $entry) => '  • ' . $entry->text, array_slice($entries, -Journal::CARRIED)));

        return <<<TEXT


            {$heading}
            {$bullets}
            TEXT;
    }
}
