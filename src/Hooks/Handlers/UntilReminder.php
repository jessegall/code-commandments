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
 * one holds, `until stuck` when it is truly blocked. Loop-safe: {@see MAX_BLOCKS} consecutive holds
 * without progress release the gate, so a wedged session can always stop (striking a condition off
 * resets the count).
 */
final class UntilReminder extends Hook
{
    /** Consecutive held stops with no condition met before the gate releases itself, to never trap a session. */
    private const int MAX_BLOCKS = 10;

    public function summary(): string
    {
        return 'Holds every stop while a `commandments until "<condition>"` gate stands (a plan takes precedence), and has you park a mid-work interjection as a condition instead of losing it.';
    }

    public function bindings(): array
    {
        return [new HookBinding('Stop'), new HookBinding('UserPromptSubmit')];
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

        return $this->block($this->hold($conditions));
    }

    /**
     * Is work in flight — a plan being ground out, or a gate already standing? Only then is a message
     * from the user an INTERJECTION that might belong in the gate rather than the turn at hand.
     */
    private function inFlight(HookEvent $event): bool
    {
        return PlanMarker::inSession($event->workspace())->isActive()
            || UntilGate::inSession($event->workspace())->isOpen();
    }

    private function triage(): string
    {
        return "Code Commandments — you are mid-work and the user just spoke. Decide which this is before "
            . "you act:\n"
            . "  • STEERING the work in hand (a correction, a change of approach, \"while you're in there…\") "
            . "— do it NOW. Do not park it; parking it is a way of not doing it.\n"
            . "  • A SEPARATE task, or one they deferred (\"later\", \"when you're done\", \"after this\"), or "
            . "anything that would derail the phase you're in — PARK it: run `vendor/bin/commandments until "
            . "\"<the task, as a statement you can verify>\"`, add it to your to-do list (TodoWrite) so the user "
            . "can see it, and carry on with what you were doing. It will hold your stop at the end, so it cannot "
            . "be lost.\n"
            . "  • Unsure? Cheap and inside the current phase → do it. Opens a new front → park it.\n"
            . "Park a task ONLY as something checkable (\"the changelog has an entry\", not \"look at the "
            . "changelog\") — you will have to verify it before you may stop.";
    }

    /**
     * @param  array<int, string>  $conditions  keyed by their stable id
     */
    private function hold(array $conditions): string
    {
        return "Code Commandments — the user set a STOP CONDITION you have not signed off yet. Do not stop. "
            . "VERIFY each condition below for real (run the command, read the file, check the output) — do not "
            . "assume it holds because you think you did the work:\n"
            . $this->numbered($conditions)
            . "\nFor each one that genuinely holds now, run `vendor/bin/commandments until met <n>` and mark its "
            . "to-do item completed (add any condition still missing from your to-do list so the user can see it); "
            . "the gate lifts "
            . "when none are left. Otherwise keep working until it holds. If you are genuinely BLOCKED and need the "
            . "user, run `vendor/bin/commandments until stuck` (NOT `until clear`) and tell them which condition you "
            . "cannot meet and why — that lets you stop once while keeping the condition in force.";
    }

    /**
     * @param  array<int, string>  $conditions  keyed by their stable id
     */
    private function released(array $conditions): string
    {
        return "Code Commandments — you have been sent back " . self::MAX_BLOCKS . " times without meeting a stop "
            . "condition, so the gate has RELEASED itself and these conditions are no longer tracked:\n"
            . $this->numbered($conditions)
            . "\nTell the user plainly that you could not meet them and what stands in the way, so they can decide "
            . "what to do. Do not set the gate again on your own.";
    }

    /**
     * @param  array<int, string>  $conditions  keyed by their stable id — printed as-is, since that
     *                                          id is the handle `until met <n>` takes
     */
    private function numbered(array $conditions): string
    {
        $lines = '';

        foreach ($conditions as $id => $condition) {
            $lines .= "\n  {$id}. {$condition}";
        }

        return $lines;
    }
}
