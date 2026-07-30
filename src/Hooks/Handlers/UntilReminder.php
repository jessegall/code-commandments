<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Until\UntilGate;
use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

/**
 * The user-set stop gate — a `Stop` hook that holds the agent while any condition set with
 * `commandments until "<condition>"` still stands ({@see UntilGate}). The plan-free sibling of
 * {@see PlanReminder}'s keep-going nudge: it needs no plan and no config opt-in, because it exists
 * only when the user explicitly asked for it. Each held stop sends the agent back in to VERIFY the
 * conditions rather than to assume them, and says how to end the gate honestly: `until met <n>` when
 * one holds, `until stuck` when it is truly blocked. It leads with the COUNT and spells out only the
 * {@see EXCERPT} oldest conditions — a gate holding dozens of parked tasks would otherwise re-print the
 * whole list on every single stop — and points at `until list` for the rest. Loop-safe: {@see MAX_BLOCKS} consecutive holds
 * without progress release the gate, so a wedged session can always stop (striking a condition off
 * resets the count).
 */
final class UntilReminder extends Hook
{
    /**
     * Consecutive held stops with no condition met before the gate releases itself, to never trap a session.
     */
    private const int MAX_BLOCKS = 10;

    /**
     * How many conditions a held stop spells out. A long gate (the user parking dozens of tasks) would
     * otherwise re-print the whole list on EVERY stop and drown the turn in text, so the message shows
     * the oldest few — the ones due next — and names the count for the rest.
     */
    private const int EXCERPT = 3;

    public function summary(): string
    {
        return 'Holds every stop while a `commandments until "<condition>"` gate stands (a plan takes precedence), and has you park a mid-work interjection as a condition instead of losing it.';
    }

    public function bindings(): array
    {
        return [new HookBinding('Stop'), new HookBinding('UserPromptSubmit'), new HookBinding('PostToolUse')];
    }

    /**
     * Count the WORK done under the gate. `until stuck` is a claim that nothing on the list can move
     * without the user, and until #419 it was accepted on assertion alone — so the gate measures whether
     * the agent actually tried between being sent back and claiming to be blocked. Only counted while a
     * gate stands: an ordinary session pays nothing for this.
     */
    protected function onPostToolUse(HookEvent $event): int
    {
        $gate = UntilGate::inSession($event->workspace());

        if ($gate->isOpen() && $this->isWork($event)) {
            $gate->recordWork();
        }

        return $this->pass();
    }

    /**
     * Is this tool use WORK — something that could move a condition — or only bookkeeping? Two moves let
     * an agent look busy without touching the problem: talking to the GATE itself (`commandments until …`,
     * which would otherwise let a `list` followed by a `stuck` count as progress) and reordering the
     * to-do list. Everything else counts, deliberately generously — reading a file, running a command and
     * editing code are all genuine attempts, and the bar this sets is only "you tried something". It
     * recognises a shell verb rather than parsing code, so no engine is owed (as with
     * {@see JudgeReminder::isGitCommit}).
     */
    private function isWork(HookEvent $event): bool
    {
        if ($event->isTool('TodoWrite')) {
            return false;
        }

        return ! $event->isTool('Bash') || ! str_contains($event->command(), 'commandments until');
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
        $gate = UntilGate::inSession($event->workspace());
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

        if ($blocks > self::MAX_BLOCKS) {
            $gate->clear();

            return $this->block($this->released($conditions));
        }

        $gate->resetWork(); // This hold is the mark the next `stuck` claim is measured against (#419).

        return $this->block($this->hold($conditions));
    }

    /**
     * Is work in flight — a plan being ground out, or a gate already standing? Only then is a message
     * from the user an INTERJECTION that might belong in the gate rather than the turn at hand.
     */
    private function inFlight(HookEvent $event): bool
    {
        $gate = UntilGate::inSession($event->workspace());

        if ($gate->isPaused()) {
            return false; // The user paused the gate to do something else in between: the whole `until`
            // machinery goes quiet — no held stop AND no "park this as a condition" nudge — until they
            // run `until resume`.
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
            . "\"add it to the to-do list\", \"don't forget to…\"), or anything that would derail the phase "
            . "you're in — PARK it, which means BOTH halves: run `vendor/bin/commandments until \"<the task, as "
            . "a statement you can verify>\"` AND add the same statement to your to-do list (TodoWrite). Then "
            . "carry on with what you were doing.\n"
            . "  • Unsure? Cheap and inside the current phase → do it. Opens a new front → park it.\n"
            . "A TO-DO ITEM IS NOT PARKING. The tracker is this session's scratch list — it holds no stop and is "
            . "gone when the session is. The gate is what brings the task back: it blocks every stop until you "
            . "have verified it. So \"add it to the to-do list\" is a DEFERRAL like any other and takes the gate "
            . "too; the tracker alone loses the task silently.\n"
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
            . "\nFor each one that genuinely holds now, run `vendor/bin/commandments until met <n>` and mark its "
            . "to-do item completed (add any condition still missing from your to-do list so the user can see it); "
            . "the gate lifts "
            . "when none are left. Otherwise keep working until it holds.\n"
            . "DRAIN THE LIST FIRST. One condition needing a decision from the user does NOT stop the others: take "
            . "the ones you can advance on your own, finish them, and leave the blocked one for last. Coming back "
            . "with one question and four conditions still untouched wastes the user's turn — coming back with one "
            . "question and everything else DONE is the whole point of the gate.\n"
            . "Only when NOTHING left on the list can move without the user, run `vendor/bin/commandments until "
            . "stuck` (NOT `until clear`) and tell them exactly which condition is blocked and what you need — that "
            . "lets you stop once while keeping every condition in force. While several conditions stand, `stuck` is "
            . "REFUSED until you have actually worked the list since reading this — a blocker you have only reasoned "
            . "about is not a blocker you have tested.";
    }

    /**
     * @param  array<int, string>  $conditions  keyed by their stable id
     */
    private function released(array $conditions): string
    {
        return "Code Commandments — you have been sent back " . self::MAX_BLOCKS . " times without meeting a stop "
            . "condition, so the gate has RELEASED itself and " . count($conditions) . " condition(s) are no longer "
            . "tracked:\n"
            . $this->excerpt($conditions, listable: false)
            . "\nTell the user plainly that you could not meet them and what stands in the way, so they can decide "
            . "what to do. Do not set the gate again on your own.";
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
     * the stable handle `until met <n>` takes, so an excerpted line is still actionable.
     *
     * @param  array<int, string>  $conditions  keyed by their stable id
     * @param  bool  $listable  whether `until list` can still show the rest (false once the gate is gone)
     */
    private function excerpt(array $conditions, bool $listable = true): string
    {
        $lines = '';

        foreach (array_slice($conditions, 0, self::EXCERPT, preserve_keys: true) as $id => $condition) {
            $lines .= "\n  {$id}. {$condition}";
        }

        $rest = count($conditions) - self::EXCERPT;

        if ($rest <= 0) {
            return $lines;
        }

        return $lines . "\n  … and {$rest} more" . ($listable
            ? " — run `vendor/bin/commandments until list` for the full list (only these first "
                . self::EXCERPT . " are shown so the gate doesn't flood every stop)."
            : ' that are gone with the gate and can no longer be listed.');
    }
}
