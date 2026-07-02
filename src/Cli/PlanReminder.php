<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\PlanExecution;
use JesseGall\CodeCommandments\StopPolicy;

/**
 * `commandments plan-reminder` — the plan-execution {@see Hook}, wired to two moments:
 *
 *  - **`PostToolUse` / `ExitPlanMode`** (a plan was just approved): records the active-plan
 *    {@see PlanMarker} and injects a nudge to load the `commandments-executing-plans` skill,
 *    concretised with THIS project's profile (branch strategy, push cadence, the `checks` commands,
 *    keep-going policy).
 *  - **`Stop`** (a turn ending): while a plan is active AND the project opted into
 *    {@see PlanExecution::keepGoing}, blocks-and-continues so the agent grinds on until the plan is
 *    done. Loop-safe — the {@see PlanMarker}'s stuck-counter caps a spinning agent, HEAD movement
 *    resets it, and {@see StopPolicy::RespectUserStops} nudges only once. Clears itself when the
 *    plan branch is back on its base (merged/abandoned), so it never leaks into later, unrelated work.
 *
 * Advisory throughout: it injects context and blocks-and-continues, never forces. A project that
 * never calls `keepGoing()` gets the approval nudge but no Stop nudge at all.
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

    protected function onPostToolUse(HookEvent $event): int
    {
        if (! $event->isTool('ExitPlanMode')) {
            return $this->pass();
        }

        $plan = $this->profile($event);
        PlanMarker::inWorktree($event->root)->activate($this->git()->head($event->root));

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

        $state = $marker->recordNudge($this->git()->head($event->root));

        if ($state->total > self::MAX_TOTAL) {
            $marker->clear(); // A plan this long was abandoned, not run — stop nudging for good.

            return $this->pass();
        }

        $capped = $plan->stopPolicy() === StopPolicy::RespectUserStops
            ? $state->total > 1              // Nudge exactly once, then honour the stop.
            : $state->stuck > self::MAX_STUCK; // Grind on, unless spinning with no new commits.

        return $capped ? $this->pass() : $this->block($this->keepGoingNudge());
    }

    private function profile(HookEvent $event): PlanExecution
    {
        return Config::load($event->root)->planExecutionSettings();
    }

    private function approvedNudge(PlanExecution $plan): string
    {
        $push = $plan->pushesEachPhase() ? ', then commit and push' : ', then commit (push once at the end)';
        $autonomy = $plan->stopPolicy() !== null
            ? "\n• Autonomy: grind through every phase without stopping — the Stop hook will keep you going until you run `plan done`."
            : '';

        return "Code Commandments — a plan was just approved. Before writing any code, load the "
            . "`commandments-executing-plans` skill (Skill tool) and follow it. This project's plan profile:\n"
            . "• Branch first: cut a new `{$plan->prefix()}<slug>` branch off `{$plan->baseBranch()}` — never work a plan on the base branch.\n"
            . "• Phases: write them as a todo list. Per phase, implement, run its scoped tests plus "
            . "`vendor/bin/commandments checks phase`{$push}. Do NOT run the full suite or `judge` between phases.\n"
            . "• End gate: run `vendor/bin/commandments checks complete` (your full checks + `judge --branch`), fix each "
            . "finding at its SOURCE, re-run until clean, then run `vendor/bin/commandments plan done`."
            . $autonomy;
    }

    private function keepGoingNudge(): string
    {
        return "Code Commandments — the plan isn't finished. Keep going: work the remaining phases, commit each, "
            . "and only stop if you genuinely need user input. When every phase is done and "
            . "`vendor/bin/commandments checks complete` is clean, run `vendor/bin/commandments plan done` to finish. "
            . "If you're truly blocked or the plan is already complete, run `plan done` and say why.";
    }
}
