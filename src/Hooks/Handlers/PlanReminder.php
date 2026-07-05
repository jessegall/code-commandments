<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\PlanProfile;
use JesseGall\CodeCommandments\StopPolicy;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Judge\Checklist;
/**
 * The plan-execution {@see Hook} wired to `PostToolUse/ExitPlanMode` and `Stop`. Records the active
 * plan via {@see PlanMarker} and injects the `commandments-executing-plans` skill nudge, then
 * blocks-and-continues while a plan is active and `keepGoing()` opted in (loop-safe, self-clearing).
 */
final class PlanReminder extends Hook
{
    /** Consecutive no-progress nudges before the keep-going Stop hook gives up, to never loop a stuck agent. */
    private const int MAX_STUCK = 4;

    /**
     * Absolute nudge ceiling: past this the marker is CLEARED, so a plan that was abandoned without a
     * `plan done` (and keeps drawing unrelated commits, which would otherwise reset {@see MAX_STUCK})
     * can never leave the keep-going hook nudging forever. No plan realistically stops this many times.
     */
    private const int MAX_TOTAL = 40;

    public function bindings(): array
    {
        return [new HookBinding('PostToolUse', 'ExitPlanMode'), new HookBinding('Stop')];
    }

    protected function onPostToolUse(HookEvent $event): int
    {
        if (! $event->isTool('ExitPlanMode')) {
            return $this->pass();
        }

        $plan = $this->profile($event);
        PlanMarker::inWorktree($event->root)->activate($this->git()->head($event->root));
        Checklist::inProject($event->root)->clearAll(); // a fresh plan starts from a clean slate — an
        // older judge's worklist would be a stale reference; it regenerates on the plan's first scan.

        return $this->inject($event, $this->approvedNudge($plan));
    }

    protected function onStop(HookEvent $event): int
    {
        $marker = PlanMarker::inWorktree($event->root);
        $plan = $this->profile($event);

        if (! $marker->isActive() || $plan->stopPolicy() === null) {
            return $this->pass(); // No plan, or keep-going not enabled — the human's stop stands.
        }

        $branch = $this->git()->currentBranch($event->root);

        if ($branch !== '' && $branch === $plan->baseBranch()) {
            $marker->clear(); // Back on the base branch — the plan is merged or abandoned; done nudging.

            return $this->pass();
        }

        $head = $this->git()->head($event->root);
        $stuckAt = $marker->stuckAt();

        if ($stuckAt !== null) {
            if ($head === $stuckAt) {
                return $this->pass(); // Blocked and no progress since — don't loop the agent; the plan stays active.
            }

            $marker->clearStuck(); // Progress since it got stuck — unblocked; resume normal keep-going.
        }

        $state = $marker->recordNudge($head);

        if ($state->total > self::MAX_TOTAL) {
            $marker->clear(); // A plan this long was abandoned, not run — stop nudging for good.

            return $this->pass();
        }

        $capped = $plan->stopPolicy() === StopPolicy::RespectUserStops
            ? $state->total > 1              // Nudge exactly once, then honour the stop.
            : $state->stuck > self::MAX_STUCK; // Grind on, unless spinning with no new commits.

        return $capped ? $this->pass() : $this->block($this->keepGoingNudge());
    }

    private function profile(HookEvent $event): PlanProfile
    {
        return Config::load($event->root)->planExecutionSettings();
    }

    private function approvedNudge(PlanProfile $plan): string
    {
        $push = $plan->pushesEachPhase() ? ', then commit and push' : ', then commit (push once at the end)';
        $autonomy = $plan->stopPolicy() !== null
            ? "\n• Autonomy: grind through every phase without stopping — the Stop hook keeps you going until you run `plan done` "
                . "(only when the plan is COMPLETE). If you get genuinely blocked and need the user, run `plan stuck` instead — it "
                . "pauses the nudges without ending the plan."
            : '';

        return "Code Commandments — a plan was just approved. Before writing any code, load the "
            . "`commandments-executing-plans` skill (Skill tool) and follow it. This project's plan profile:\n"
            . "• Branch first: cut a new `{$plan->prefix()}<slug>` branch off `{$plan->baseBranch()}` — never work a plan on the base branch.\n"
            . "• Phases: write them as a todo list. Per phase, implement, run its scoped tests plus "
            . "`vendor/bin/commandments checks phase`{$push}. Do NOT run the full suite or `judge` between phases.\n"
            . "• End gate: run `vendor/bin/commandments checks complete` (your full checks + `judge --branch`), fix each "
            . "finding at its SOURCE, re-run until clean, then run `vendor/bin/commandments plan done`."
            . $this->constraintsSection($plan)
            . $this->testingSection($plan)
            . $autonomy;
    }

    /**
     * The constraints bullet for the approval nudge — always ask the user for this run's invariants (the
     * hook can't ask; it instructs the agent to), listing the project's global ones so the agent knows
     * they are already in force. The completion gate is what makes it stick.
     */
    private function constraintsSection(PlanProfile $plan): string
    {
        $global = '';

        foreach ($plan->constraints() as $rule) {
            $global .= "\n    - {$rule}";
        }

        return "\n• Constraints: ask the user (AskUserQuestion) whether this run has any architectural "
            . "constraints to hold to — invariants `judge` can't check (e.g. \"no logic in the frontend\") — "
            . "and record each with `vendor/bin/commandments constraints add \"<rule>\"`. The completion gate "
            . "blocks `plan done` until you review your whole branch diff against every constraint "
            . "(`vendor/bin/commandments constraints check` → fix → `constraints verified`)."
            . ($global === '' ? '' : "\n  Already in force (global):{$global}");
    }

    /**
     * The testing-methodology bullet for the approval nudge — tells the agent to ask the user (a second
     * AskUserQuestion, alongside constraints) how tests are handled for this run, offering the standard
     * methods, the project's configured default (when set) as "use the project's test flow", and an open
     * custom option. The choice is recorded with `commandments testing set`, then re-surfaced through the
     * run by {@see TestingReminder}. No hard gate — a testing style is verified by the phase tests, not a diff.
     */
    private function testingSection(PlanProfile $plan): string
    {
        $configured = trim($plan->testFlow());

        $useConfigured = $configured === '' ? '' : "\n    - Use the project's configured test flow: \"{$configured}\"";

        return "\n• Testing methodology: ask the user (AskUserQuestion) how tests should be written and run for "
            . "this plan, and record the answer with `vendor/bin/commandments testing set \"<methodology>\"`. "
            . "Offer these options:"
            . $useConfigured
            . "\n    - Write AND run the tests for each phase before committing it"
            . "\n    - Write no tests until the very end, then add them all in one pass"
            . "\n    - Only ADD new tests for this work — don't touch pre-existing failing tests"
            . "\n    - Only FIX broken tests — don't add new ones"
            . "\n    - Custom (let the user describe their own)"
            . ($configured === ''
                ? "\n  No project default is configured, so don't offer the \"configured test flow\" option."
                : "\n  When the user just takes the configured default, `testing set` it verbatim.");
    }

    private function keepGoingNudge(): string
    {
        return "Code Commandments — the plan isn't finished. Keep going: work the remaining phases, commit each, "
            . "and only stop if you genuinely need user input. When every phase is done and "
            . "`vendor/bin/commandments checks complete` is clean, run `vendor/bin/commandments plan done` to finish. "
            . "If you are genuinely BLOCKED and need the user, run `vendor/bin/commandments plan stuck` (NOT `plan done` "
            . "— you may only `done` a COMPLETE plan): it pauses these nudges while keeping the plan active, and tell "
            . "the user what you're blocked on. Nudging resumes on your own once you make progress.";
    }
}
