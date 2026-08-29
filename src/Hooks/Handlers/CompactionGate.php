<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Support\Binary;

/**
 * Stands in front of an AUTOMATIC compaction — the moment a session loses what the user decided, and never
 * a compaction the user asked for themselves. The first attempt is cancelled and the agent sent back to pin
 * anything it would be lost without; the attempt after that goes through carrying instructions built from
 * the {@see Journal} that tell the summariser what must survive. Cancelling happens ONCE because a blocked
 * compaction does not re-run at once — the harness carries on uncompacted and the context only grows — so
 * {@see Journal::isPreparedForCompaction} guarantees the next attempt lands, and expires so a compaction
 * that never came back leaves no yes behind it.
 */
final class CompactionGate extends Hook
{
    /**
     * How many pinned facts and open spans the instructions carry. The instructions ride in front of a
     * summarisation prompt, so they must be short enough not to crowd out the conversation they are about.
     */
    private const int CARRIED = 12;

    /**
     * The compaction this gate is for — the one the harness runs because the context filled up.
     */
    private const string AUTOMATIC = 'auto';

    public function summary(): string
    {
        return 'Cancels the first automatic compaction so you can pin what must survive, then writes the compaction its own instructions from the journal.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PreCompact', self::AUTOMATIC)];
    }

    protected function onPreCompact(HookEvent $event): int
    {
        if ($event->trigger() !== self::AUTOMATIC) {
            return $this->pass(); // A compaction the user asked for is theirs; the binding says so too, and the hook holds to it either way.
        }

        $journal = Journal::inSession($event->workspace());

        if (! $journal->isPreparedForCompaction()) {
            $journal->prepare();

            return $this->block($this->warning($event, $journal));
        }

        return $this->instruct($this->instructions($journal));
    }

    /**
     * What the agent is told when the compaction is cancelled — one turn to record what only it knows,
     * before the summary takes it.
     */
    private function warning(HookEvent $event, Journal $journal): string
    {
        $binary = Binary::in($event->root);
        $open = $this->listing('You have work OPEN and unfinished — say where each stands, so the far side knows:', $journal->openSpans());

        return <<<TEXT
            Code Commandments — the context is FULL and a compaction is about to run. It has been held for ONE
            turn, and will proceed on the next attempt whatever you do now.

            A compaction keeps what was DONE and loses what was DECIDED — the ruling the user gave once, the
            approach you changed your mind about, the thing you are half-way through. Spend this turn recording
            what you would be lost without:

              {$binary} journal remember "<the fact you must not lose>"

            Pin the user's standing rulings, the constraint you keep nearly breaking, and the decision behind the
            work in hand. Then close or restate any work you have open. Do NOT start anything new.

            `{$binary} journal instructions` is the whole brief, if you need it.
            {$open}
            TEXT;
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

        $bullets = implode("\n", array_map(fn (Entry $entry) => '  • ' . $entry->text, array_slice($entries, -self::CARRIED)));

        return <<<TEXT


            {$heading}
            {$bullets}
            TEXT;
    }
}
