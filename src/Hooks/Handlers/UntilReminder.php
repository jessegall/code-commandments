<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

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
        return 'Holds every stop while a `commandments until "<condition>"` gate stands, sending you back to verify it.';
    }

    public function bindings(): array
    {
        return [new HookBinding('Stop')];
    }

    protected function onStop(HookEvent $event): int
    {
        $gate = UntilGate::inSession($event->workspace());
        $conditions = $gate->all();

        if ($conditions === []) {
            return $this->pass(); // No gate — the stop stands.
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
     * @param  list<string>  $conditions
     */
    private function hold(array $conditions): string
    {
        return "Code Commandments — the user set a STOP CONDITION you have not signed off yet. Do not stop. "
            . "VERIFY each condition below for real (run the command, read the file, check the output) — do not "
            . "assume it holds because you think you did the work:\n"
            . $this->numbered($conditions)
            . "\nFor each one that genuinely holds now, run `vendor/bin/commandments until met <n>`; the gate lifts "
            . "when none are left. Otherwise keep working until it holds. If you are genuinely BLOCKED and need the "
            . "user, run `vendor/bin/commandments until stuck` (NOT `until clear`) and tell them which condition you "
            . "cannot meet and why — that lets you stop once while keeping the condition in force.";
    }

    /**
     * @param  list<string>  $conditions
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
     * @param  list<string>  $conditions
     */
    private function numbered(array $conditions): string
    {
        $lines = '';

        foreach ($conditions as $index => $condition) {
            $lines .= "\n  " . ($index + 1) . ". {$condition}";
        }

        return $lines;
    }
}
