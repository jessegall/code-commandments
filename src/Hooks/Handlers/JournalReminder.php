<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\StopHookCap;
use JesseGall\CodeCommandments\Support\Binary;

/**
 * Keeps the journal worth reading. A vocabulary nobody is reminded of decays to nothing, so the tags
 * resurface as the agent works; and work left open at the end of a turn is the one thing a later reader
 * cannot reconstruct, so a stop is held until the agent says where it got to.
 */
final class JournalReminder extends Hook
{
    /**
     * How many tool uses pass between reminders — the same heartbeat the cardinal rule keeps.
     */
    private const int INTERVAL = 25;

    /**
     * How many stops may be held for unfinished work. It is a reminder, not a gate: the work may genuinely
     * be unfinished, and the point is that it be SAID so, once.
     */
    private const int HOLDS = 1;

    /**
     * How many open pieces of work the one-line word names before it becomes a list nobody reads.
     */
    private const int NAMED = 3;

    public function summary(): string
    {
        return 'Resurfaces the journal tags as you work, and holds one stop while work you declared is still open.';
    }

    public function bindings(): array
    {
        return [new HookBinding('PostToolUse'), new HookBinding('Stop')];
    }

    protected function onPostToolUse(HookEvent $event): int
    {
        $counter = Counter::named($event->workspace(), 'journal-tags', 'resurfaces the journal tag vocabulary once every 25 tool uses', every: self::INTERVAL);

        if (! $counter->due()) {
            return $this->pass();
        }

        return $this->quietly($event, $this->reminder($event));
    }

    /**
     * A turn ending on work that was never closed leaves the next reader — after a compaction, or tomorrow
     * — a start with no end and no idea whether it was finished.
     *
     * The two mistakes are not alike, and that decides the shape of this. A SPURIOUS end is harmless: it
     * closes something already done. A MISSING one is invisible, and invisible to the agent most of all,
     * which believes it finished. So the count is said EVERY time, at the moment the agent thinks it is
     * done, which is when it can still act on it — and the turn is HELD only while there is budget for it,
     * because being told is the point and being stopped repeatedly is not.
     */
    protected function onStop(HookEvent $event): int
    {
        $open = Journal::inSession($event->sessionWorkspace())->openSpans();

        if ($open === []) {
            return $this->pass(); // Nothing open costs nothing to report.
        }

        if (StopHookCap::budget(self::HOLDS) < 1) {
            return $this->quietly($event, $this->standing($open));
        }

        $binary = Binary::in($event->root);
        $work = implode("\n", array_map(fn (Entry $entry) => '  • ' . $entry->text, $open));
        $end = Tag::End->marker();

        return $this->block(<<<TEXT
            Code Commandments — you declared work that is still open:

            {$work}

            Close it before you stop. If it is finished, say so — `{$end} <the same words>`. If it is not,
            say where it got to and what the next step is, so a reader on the far side of a compaction is
            not left with a start and no end.

            `{$binary} journal open` lists it.
            TEXT);
    }

    /**
     * The one-line word for a stop this may no longer hold — said anyway, because the agent stopping is
     * exactly the agent that believes there is nothing left open.
     *
     * @param  list<Entry>  $open
     */
    private function standing(array $open): string
    {
        $titles = implode('; ', array_map(fn (Entry $entry) => $entry->text, array_slice($open, 0, self::NAMED)));

        return sprintf(
            'Code Commandments — you still have %d piece(s) of work open: %s. Close each with %s, or say where it stands.',
            count($open),
            $titles,
            Tag::End->marker(),
        );
    }

    private function reminder(HookEvent $event): string
    {
        $binary = Binary::in($event->root);
        $tags = Tag::vocabulary();

        return <<<TEXT
            Code Commandments — the journal. A compaction keeps what was DONE and loses what was DECIDED, so
            say what your messages carry. Tag the front of a message with one of:

            {$tags}

            Declare a piece of work before you change anything and close it when it is done — a start with
            no end is what tells the next reader work was left in flight. And when something is genuinely
            important, do not merely say it:

              {$binary} journal remember "<the fact you must not lose>"

            That outlives every compaction and is written into the summariser's own instructions.
            TEXT;
    }
}
