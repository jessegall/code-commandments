<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Reading;
use JesseGall\CodeCommandments\Cli\Journal\Session;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Hooks\Holes;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\Reminders;
use JesseGall\CodeCommandments\Hooks\StopHookCap;
use JesseGall\CodeCommandments\Support\Binary;
use JesseGall\PhpTypes\Option;

/**
 * Keeps the journal worth reading. A vocabulary nobody is reminded of decays to nothing, so the tags
 * resurface as the agent works; and work left open at the end of a turn is the one thing a later reader
 * cannot reconstruct, so a stop is held until the agent says where it got to.
 */
final class JournalReminder extends Hook
{
    /**
     * How many tool calls of SILENCE earn a nudge. Counted since anything was last tagged or pinned, not
     * since the nudge last fired — so recording something clears the debt and a stretch of quiet earns
     * it back, where firing on a fixed rhythm would be a metronome to tune out.
     */
    private const int QUIET = 10;

    /**
     * How much silence stops being a nudge and becomes a refusal. A stretch this long with nothing
     * recorded is a stretch whose reasoning is already gone — and the fix costs one line, which is what
     * makes insisting on it fair.
     */
    private const int ENFORCED = 25;

    /**
     * How many stops may be held for unfinished work. It is a reminder, not a gate: the work may genuinely
     * be unfinished, and the point is that it be SAID so, once.
     */
    private const int HOLDS = 1;

    /**
     * How many open pieces of work the one-line word names before it becomes a list nobody reads.
     */
    private const int NAMED = 3;

    /**
     * How long a silence earns the stop question. Recording anything pays the debt, so an agent that
     * keeps its record is never asked, and one that has written nothing is asked once per stretch — and
     * the stretch is long, because a question asked at every stop is one nobody reads by the third time.
     */
    private const int A_STRETCH = 25;

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
        $journal = Journal::inSession($event->sessionWorkspace());

        $quiet = $journal->countCall();

        // A stretch this long has already lost its reasoning, and the fix costs one line — which is what
        // makes insisting fair rather than harsh.
        if ($quiet >= self::ENFORCED) {
            $said = $this->enforced($event, $journal, $quiet);

            // A refusal with nothing to say is not a refusal — it is a dead end the agent cannot act on.
            // So the gate goes with the words.
            return $said === '' ? $this->pass() : $this->block($said);
        }

        // Once per STRETCH of silence, not once per call past the threshold. Nagging every call is the
        // metronome the debt-and-payment shape exists to avoid: recording something clears it, and
        // staying quiet earns exactly one more.
        if ($quiet < self::QUIET || $quiet % self::QUIET !== 0) {
            return $this->pass();
        }

        $said = $this->reminder($event, $journal, $quiet);

        return $said === '' ? $this->pass() : $this->quietly($event, $said);
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
        $journal = Journal::inSession($event->sessionWorkspace());
        $unheard = $this->unheard($event);
        $open = $journal->openSpans();

        if ($open === [] && $unheard === '' && $event->hasPendingBackgroundWork()) {
            return $this->pass(); // Parked on a worker: the turn has not ended, so there is nothing to ask about.
        }

        if ($open === [] && $unheard === '') {
            // SILENCE is the debt and RECORDING pays it — the shape the per-call nudge already uses.
            // Pacing on total work instead asks again every stretch however much has been written down,
            // which is the metronome this whole thread exists to remove.
            if ($journal->quietFor() < self::A_STRETCH) {
                return $this->pass();
            }

            $habit = $this->habit($event);

            return $habit === '' ? $this->pass() : $this->quietly($event, $habit);
        }

        if ($unheard !== '') {
            return $this->block($unheard);
        }

        if (StopHookCap::budget(self::HOLDS) < 1) {
            $standing = $this->standing($open);

            return $standing === '' ? $this->pass() : $this->quietly($event, $standing);
        }

        $holes = Holes::none()
            ->with('work', implode("\n", array_map(fn (Entry $entry) => '  • ' . $entry->text, $open)))
            ->with('end', Tag::End->marker())
            ->with('binary', Binary::in($event->root));

        $said = $this->words('journal-open', $holes);

        return $said === '' ? $this->pass() : $this->block($said);
    }

    /**
     * What a reminder amounts to as output — the package's name in front of it, and NOTHING where the
     * words cannot be read. Every caller treats the empty string as "say nothing and do not hold the
     * turn", because a gate whose reason is missing is a refusal the agent cannot act on.
     */
    private function words(string $name, Holes $holes): string
    {
        return Reminders::shipped()
            ->say($name, $holes)
            ->mapOr('', static fn (string $said): string => 'Code Commandments — ' . $said);
    }

    /**
     * What the agent SAID that the record never heard. This is the one thing it cannot check from where it
     * sits: "I stopped tagging" and "I tagged and the tool did not hear me" are the same silence, and the
     * second leaves it believing it closed work that is still open. Read once, at the end of a turn.
     */
    private function unheard(HookEvent $event): string
    {
        if ($event->transcriptPath() === '') {
            return '';
        }

        $session = new Session($event->sessionId(), $event->transcriptPath(), 0, '');
        $verdict = new Reading($session, $event->sessionWorkspace()->root())->verify();

        if (! str_contains($verdict, 'NOT FILED')) {
            return '';
        }

        $holes = Holes::none()
            ->with('verdict', $verdict)
            ->with('binary', Binary::in($event->root));

        return $this->words('journal-unheard', $holes);
    }

    /**
     * The one-line word for a stop this may no longer hold — said anyway, because the agent stopping is
     * exactly the agent that believes there is nothing left open.
     *
     * @param  list<Entry>  $open
     */
    private function standing(array $open): string
    {
        $holes = Holes::none()
            ->with('count', count($open))
            ->with('work', implode('; ', array_map(fn (Entry $entry) => $entry->text, array_slice($open, 0, self::NAMED))))
            ->with('end', Tag::End->marker());

        return $this->words('journal-standing', $holes);
    }

    /**
     * What a stop with nothing open still gets. The turn that closed its work is the one most likely to
     * carry a ruling or a discovery nobody wrote down — and a stop is the last moment it can be, since the
     * next reader arrives on the far side of a compaction with only the record.
     *
     * Quiet, so it costs the user nothing to be reminded on every stop rather than on the stops a counter
     * happened to pick.
     */
    private function habit(HookEvent $event): string
    {
        return $this->words('journal-habit', Holes::none()->with('binary', Binary::in($event->root)));
    }

    /**
     * ONE line, and it says its own numbers. "3 tags in 40 tool calls" is a fact somebody can act on;
     * re-printing the vocabulary every time is wallpaper, and a nudge that arrives with nothing new in it
     * teaches a reader to skim the block that will eventually hold something.
     *
     * The vocabulary is not repeated — `journal instructions` holds it, and a reader who needs it can ask
     * once rather than be shown it forty times.
     */
    private function reminder(HookEvent $event, Journal $journal, int $quiet): string
    {
        $tagged = $journal->tagged();

        // Counted HERE, at the moment the line is said. A nudge arrives wearing the voice of the system,
        // so a number in one does not read as stale — it reads as authoritative.
        $holes = Holes::none()
            ->with('tagged', $tagged === 1 ? '1 tag' : "{$tagged} tags")
            ->with('quiet', $quiet)
            ->with('binary', Binary::in($event->root));

        return $this->words('journal-quiet', $holes);
    }

    /**
     * Said when the silence has gone past asking. It names the number, says what one line would cost,
     * and is CHEAP TO SATISFY — a gate that can only be answered by real work would be paid for in the
     * thing it exists to protect.
     */
    private function enforced(HookEvent $event, Journal $journal, int $quiet): string
    {
        $holes = Holes::none()
            ->with('quiet', $quiet)
            ->with('tagged', $journal->tagged())
            ->with('binary', Binary::in($event->root));

        return $this->words('journal-enforced', $holes);
    }
}
