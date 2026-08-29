<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\StopCondition\StopConditionGate;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\StopHookCap;

/**
 * The user-set stop gate — a `Stop` hook that holds the agent while any condition set with
 * `commandments stop-condition "<condition>"` still stands ({@see StopConditionGate}). The plan-free sibling of
 * {@see PlanReminder}'s keep-going nudge: it needs no plan and no config opt-in, because it exists
 * only when the user explicitly asked for it. Each held stop sends the agent back in to VERIFY the
 * conditions rather than to assume them, and says how to end the gate honestly: `stop-condition met <n>` when
 * one holds, `stop-condition stuck` when it is truly blocked. It leads with the COUNT and spells out only the
 * {@see EXCERPT} oldest conditions — a gate holding dozens of parked tasks would otherwise re-print the
 * whole list on every single stop — and points at `stop-condition list` for the rest.
 * Loop-safe: {@see MAX_BLOCKS} consecutive holds
 * without progress release the gate, so a wedged session can always stop (striking a condition off
 * resets the count).
 */
final class StopConditionReminder extends Hook
{
    /**
     * Consecutive held stops with no condition met before the gate releases itself, to never trap a session.
     *
     * Generous on purpose. This is a runaway backstop, not a work budget: a session draining a queue of
     * inbound issues holds many stops between one condition and the next, and releasing the gate on it
     * sets the user's conditions aside just as the work is going well. Ten was low enough to fire during
     * ordinary progress. Clamped by {@see StopHookCap} — the harness's own cap is the real ceiling, and
     * a number above it never runs.
     */
    private const int MAX_BLOCKS = 50;

    /**
     * How many conditions a held stop spells out. A long gate (the user parking dozens of tasks) would
     * otherwise re-print the whole list on EVERY stop and drown the turn in text, so the message shows
     * the oldest few — the ones due next — and names the count for the rest.
     */
    private const int EXCERPT = 3;

    public function summary(): string
    {
        return 'Holds every stop while a `commandments stop-condition "<condition>"` gate stands (a plan takes precedence), and has you park a mid-work interjection as a condition instead of losing it.';
    }

    public function bindings(): array
    {
        return [new HookBinding('Stop'), new HookBinding('UserPromptSubmit'), new HookBinding('PostToolUse')];
    }

    /**
     * Watch the WORK done under the gate, and only while a gate stands, so an ordinary session pays
     * nothing for it. Going back to work VOIDS a half-answered `stuck` claim — an agent that took the
     * challenge and carried on starts the next claim from the beginning.
     */
    protected function onPostToolUse(HookEvent $event): int
    {
        $gate = StopConditionGate::inSession($event->workspace());

        if (! $gate->isOpen() || ! $this->isWork($event)) {
            return $this->pass();
        }

        $gate->dropClaim(); // Back at work, so the half-answered `stuck` claim is void.
        $gate->recordWork();

        return $this->pass();
    }

    /**
     * Is this tool use WORK — something that could move a condition — or only bookkeeping? Talking to the
     * GATE itself (`commandments stop-condition …`) would otherwise let a `list` followed by a `stuck`
     * count as progress. Everything else counts, deliberately generously — reading a file, running a
     * command and editing code are all genuine attempts, and the bar this sets is only "you tried
     * something". It recognises a shell verb rather than parsing code, so no engine is owed (as with
     * {@see JudgeReminder::isGitCommit}).
     */
    private function isWork(HookEvent $event): bool
    {
        return ! $event->isTool('Bash') || ! str_contains($event->command(), 'commandments stop-condition');
    }

    /**
     * The user spoke while work is in flight. A hook cannot read intent, so it puts the TRIAGE in front
     * of the agent: steering the work in hand is done NOW, a separate or explicitly-later task is parked
     * as a condition so it resurfaces at the end instead of being lost. Silent when nothing is in flight
     * — an ordinary conversation is not taxed with it.
     */
    protected function onUserPromptSubmit(HookEvent $event): int
    {
        if (! $this->inFlight($event)) {
            return $this->pass();
        }

        return $this->inject($event, $this->triage());
    }

    protected function onStop(HookEvent $event): int
    {
        $gate = StopConditionGate::inSession($event->workspace());
        $conditions = $gate->all();

        if ($conditions === []) {
            return $this->pass(); // No gate — the stop stands.
        }

        if (PlanMarker::inSession($event->workspace())->isActive()) {
            return $this->pass(); // A plan owns the stop: ONE hook pushes the agent back in, and the
            // parked conditions must not burn their release cap during a long grind. They take over the
            // moment `plan done` clears the plan — which is exactly "at the end".
        }

        if ($gate->isStuck()) {
            $gate->clearStuck();

            return $this->pass(); // One-shot: the agent said it's blocked, so let it hand back to the
            // user. The conditions stay in force and hold again the moment it continues.
        }

        $blocks = $gate->recordBlock();

        if ($blocks > StopHookCap::budget(self::MAX_BLOCKS)) {
            $gate->pause(); // SET ASIDE, never dropped: the cap protects the session from spinning, and
            // destroying what the user asked for is not part of that bargain — a `resume` puts it back.

            return $this->block($this->released($conditions));
        }

        return $this->block($this->hold($conditions));
    }

    /**
     * Is work in flight — a plan being ground out, or a gate already standing? Only then is a message
     * from the user an INTERJECTION that might belong in the gate rather than the turn at hand.
     */
    private function inFlight(HookEvent $event): bool
    {
        $gate = StopConditionGate::inSession($event->workspace());

        if ($gate->isPaused()) {
            return false; // The user paused the gate to do something else in between: the whole `until`
            // machinery goes quiet — no held stop AND no "park this as a condition" nudge — until they
            // run `stop-condition resume`.
        }

        return PlanMarker::inSession($event->workspace())->isActive() || $gate->isOpen();
    }

    private function triage(): string
    {
        return "Code Commandments — you are mid-work and the user just spoke. Decide which this is before "
            . "you act:\n"
            . "  • STEERING the work in hand (a correction, a change of approach, \"while you're in there…\") "
            . "— do it NOW. Do not park it; parking it is a way of not doing it.\n"
            . "  • A SEPARATE task, or one they deferred (\"later\", \"when you're done\", \"after this\", "
            . "\"don't forget to…\"), or anything that would derail the phase "
            . "you're in — PARK it: run `vendor/bin/commandments stop-condition \"<the task, as "
            . "a statement you can verify>\"`, then carry on with what you were doing.\n"
            . "  • Unsure? Cheap and inside the current phase → do it. Opens a new front → park it.\n"
            . "The gate is what brings a parked task back: it blocks every stop until you have verified it.\n"
            . "Park a task ONLY as something checkable (\"the changelog has an entry\", not \"look at the "
            . "changelog\") — you will have to verify it before you may stop.";
    }

    /**
     * @param  array<int, string>  $conditions  keyed by their stable id
     */
    private function hold(array $conditions): string
    {
        return "Code Commandments — " . $this->standing($conditions) . " you have not signed off yet. Do not stop. "
            . "VERIFY each condition for real (run the command, read the file, check the output) — do not "
            . "assume it holds because you think you did the work.\n"
            . $this->excerpt($conditions)
            . "\nFor each one that genuinely holds now, run `vendor/bin/commandments stop-condition met <n>`; "
            . "the gate lifts "
            . "when none are left. Otherwise keep working until it holds.\n"
            . "DRAIN THE LIST FIRST. One condition needing a decision from the user does NOT stop the others: take "
            . "the ones you can advance on your own, finish them, and leave the blocked one for last. Coming back "
            . "with one question and four conditions still untouched wastes the user's turn — coming back with one "
            . "question and everything else DONE is the whole point of the gate.\n"
            . "`stuck` IS NOT FOR A BLOCKED ITEM — it is for a blocked LIST. \"The thing in front of me needs the "
            . "user\" is not being stuck; that is ONE blocked item and the rest of the list still to work. Leave the "
            . "blocked one for last and carry on with the others. When a condition genuinely needs the user, say so "
            . "AGAINST THAT CONDITION as you meet it — `vendor/bin/commandments stop-condition blocked <n> --reason=\"<what "
            . "only the user can give>\"` — and carry on with the rest. Once EVERY standing condition carries a "
            . "reason, `vendor/bin/commandments stop-condition stuck` (NOT `stop-condition clear`) releases one stop. Nothing you said "
            . "before this message counts: being sent back in DROPS every block, so the claim is about the list as it "
            . "stands now.\n\nAnd say this to the USER rather than working around it: `commandments stop-condition "
            . "pause` is THEIR switch — it sets the whole gate aside with every condition kept verbatim, and "
            . "`resume` puts it back. A gate holding a stop on work that is not what you are doing is a gate to "
            . "tell them about, not one to route around.";
    }

    /**
     * The cap has fired: nothing holds the next stop, but what the user asked for is SET ASIDE rather than
     * deleted, so the decision about it stays theirs ({@see StopConditionGate::pause}).
     *
     * @param  array<int, string>  $conditions  keyed by their stable id
     */
    private function released(array $conditions): string
    {
        return "Code Commandments — you have been sent back " . StopHookCap::budget(self::MAX_BLOCKS) . " times without meeting a stop "
            . "condition, so the gate has RELEASED itself: nothing holds your next stop, and "
            . count($conditions) . " condition(s) are SET ASIDE, kept verbatim:\n"
            . $this->excerpt($conditions, listable: false)
            . "\nTell the user plainly that you could not meet them and what stands in the way, and that "
            . "`commandments stop-condition resume` puts them back in force, so they can decide what to do. Do not resume "
            . "or re-set the gate on your own.";
    }

    /**
     * "The user set N stop conditions" — the COUNT leads the message, so a long gate is stated as a
     * number instead of as a wall of text the agent has to measure by eye.
     *
     * @param  array<int, string>  $conditions
     */
    private function standing(array $conditions): string
    {
        $count = count($conditions);

        return $count === 1
            ? 'the user set a STOP CONDITION'
            : "the user set {$count} STOP CONDITIONS";
    }

    /**
     * The conditions as the agent reads them: the {@see EXCERPT} oldest — the ones due next — and, when
     * more stand behind them, how many and where the whole list is. A held stop repeats on EVERY stop,
     * so spelling out a 50-condition gate each time costs more context than it buys; the id shown is
     * the stable handle `stop-condition met <n>` takes, so an excerpted line is still actionable.
     *
     * @param  array<int, string>  $conditions  keyed by their stable id
     * @param  bool  $listable  whether `stop-condition list` can still show the rest (false once the gate is gone)
     */
    private function excerpt(array $conditions, bool $listable = true): string
    {
        $lines = '';

        // The most RECENT, never the lowest-numbered. Ids only ever rise, so taking the first shows the
        // OLDEST — the ones furthest from whatever is being worked on now, which teaches a reader that
        // the sample is irrelevant and so that the list is.
        foreach (array_slice($conditions, -self::EXCERPT, null, preserve_keys: true) as $id => $condition) {
            $lines .= "\n  {$id}. {$condition}";
        }

        $rest = count($conditions) - self::EXCERPT;

        if ($rest <= 0) {
            return $lines;
        }

        return $lines . "\n  … and {$rest} more" . ($listable
            ? " — run `vendor/bin/commandments stop-condition list` for the full list (the "
                . self::EXCERPT . " most recent are shown so the gate doesn't flood every stop)."
            : ' that are gone with the gate and can no longer be listed.');
    }
}
