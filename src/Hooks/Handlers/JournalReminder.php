<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Reading;
use JesseGall\CodeCommandments\Cli\Journal\Session;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
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
    private const int ENFORCED = 50;

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
            return $this->block($this->enforced($event, $journal, $quiet));
        }

        // Once per STRETCH of silence, not once per call past the threshold. Nagging every call is the
        // metronome the debt-and-payment shape exists to avoid: recording something clears it, and
        // staying quiet earns exactly one more.
        if ($quiet < self::QUIET || $quiet % self::QUIET !== 0) {
            return $this->pass();
        }

        return $this->quietly($event, $this->reminder($event, $journal, $quiet));
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
            return $journal->quietFor() >= self::A_STRETCH
                ? $this->quietly($event, $this->habit($event))
                : $this->pass();
        }

        if ($unheard !== '') {
            return $this->block($unheard);
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

        $binary = Binary::in($event->root);

        return <<<TEXT
            Code Commandments — the journal did NOT hear some of what you said:

            {$verdict}

            You may believe you closed work that is still open, or pinned a fact that was never kept. Say
            those lines again, on their own line, before you stop — and `{$binary} journal verify` checks it.
            TEXT;
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
        $binary = Binary::in($event->root);

        return <<<TEXT
            Code Commandments — nothing is open, so before you stop: did this turn produce a ruling, a
            discovery, or a reason a later reader would need? A fact nobody wrote down is one the next
            reader re-derives. Each kind has its OWN home, and only the first of these is a pin:

              a FACT or a RULING that must outlive a compaction  →  `{$binary} journal remember "<it>"`
              a FINDING, an open defect, work still owed         →  the plan
              a STATUS — who holds what, what was measured       →  the board

            A pin cannot be superseded, so anything that ROTS — a count, a defect that will be fixed, a
            list of work — becomes a confident falsehood the moment it changes. Those belong in the two
            places that can be updated.
            TEXT;
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
        $binary = Binary::in($event->root);
        $tagged = $journal->tagged();
        $said = $tagged === 1 ? '1 tag' : "{$tagged} tags";

        return "Code Commandments — {$said} this session, and nothing recorded in the last "
            . $quiet . " tool calls. A ruling you do not write down is one the next reader re-derives: "
            . "open a line with [!discovery]/[!correction]/[!update], or `{$binary} journal remember \"<the fact>\"`.";
    }

    /**
     * Said when the silence has gone past asking. It names the number, says what one line would cost,
     * and is CHEAP TO SATISFY — a gate that can only be answered by real work would be paid for in the
     * thing it exists to protect.
     */
    private function enforced(HookEvent $event, Journal $journal, int $quiet): string
    {
        $binary = Binary::in($event->root);
        $tagged = $journal->tagged();

        return "Code Commandments — {$quiet} tool calls and nothing recorded. {$tagged} tag(s) this "
            . "session. Whatever you have decided in that stretch is now only in your head, and a "
            . "compaction takes exactly that. Record ONE line before the next call — open a line with "
            . "[!discovery], [!correction] or [!update], or `{$binary} journal remember \"<the fact>\"`. "
            . "It clears the moment anything is filed.";
    }
}
