<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks\Handlers;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\PlanMode;
use JesseGall\CodeCommandments\PlanProfile;

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

    public function summary(): string
    {
        return "On plan approval loads the executing-plans skill with your profile; on stop, keeps you going until `plan done` per the plan `mode()` (Ask/Supervised/Autonomous/Relentless).";
    }

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
        $mode = $this->profile($event)->mode();

        if (! $marker->isActive() || $mode === null || ! $mode->keepsGoing()) {
            return $this->pass(); // No plan, unmanaged, or Ask (a start-gate) — the human's stop stands.
        }

        $branch = $this->git()->currentBranch($event->root);

        if ($branch !== '' && $branch === $this->profile($event)->baseBranch()) {
            $marker->clear(); // Back on the base branch — the plan is merged or abandoned; done nudging.

            return $this->pass();
        }

        if ($marker->stuckAt() !== null) {
            $marker->clearStuck();

            // A stuck pause is honoured ONLY where the mode allows it (Autonomous): suppress this one stop
            // so a blocked agent isn't looped, then clear the one-shot signal so keep-going resumes on the
            // next progress. Relentless has no waiting — a stuck signal is cleared but the agent is pushed
            // straight back in to SKIP the blocker and keep going.
            if ($mode->allowsStuck()) {
                return $this->pass();
            }
        }

        $state = $marker->recordNudge($this->git()->head($event->root));

        if ($state->total > self::MAX_TOTAL) {
            $marker->clear(); // A plan this long was abandoned, not run — stop nudging for good.

            return $this->pass();
        }

        // Relentless never caps on no-progress (only the absolute MAX_TOTAL backstop ends it); Supervised
        // nudges once; Autonomous grinds until it's clearly spinning with no new commits.
        $capped = match (true) {
            $mode->neverStops() => false,
            $mode->nudgesOnce() => $state->total > 1,
            default => $state->stuck > self::MAX_STUCK,
        };

        return $capped ? $this->pass() : $this->block($this->keepGoingNudge($mode));
    }

    private function profile(HookEvent $event): PlanProfile
    {
        return Config::load($event->root)->planExecutionSettings();
    }

    private function approvedNudge(PlanProfile $plan): string
    {
        $push = $plan->pushesEachPhase() ? ', then commit and push' : ', then commit (push once at the end)';

        return "Code Commandments — a plan was just approved. Before writing any code, load the "
            . "`commandments-executing-plans` skill (Skill tool) and follow it. This project's plan profile:\n"
            . "• Branch first: cut a new `{$plan->prefix()}<slug>` branch off `{$plan->baseBranch()}` — never work a plan on the base branch.\n"
            . "• Phases: write them as a todo list. Per phase, implement, run its scoped tests plus "
            . "`vendor/bin/commandments checks phase`{$push}. Do NOT run the full suite or `judge` between phases.\n"
            . "• End gate: run `vendor/bin/commandments checks complete` (your full checks + `judge --branch`), fix each "
            . "finding at its SOURCE, re-run until clean, then run `vendor/bin/commandments plan done`."
            . $this->constraintsSection($plan)
            . $this->testingSection($plan)
            . $this->workingStateSection($plan)
            . $this->autonomySection($plan->mode());
    }

    /**
     * The mode-specific autonomy bullet for the approval nudge — how hard to push and what to do when
     * blocked. Silent when the project sets no mode. {@see PlanMode::Relentless} is the one that answers the
     * user's demand: never stop, never ask, SKIP a blocker and keep going — and it never mentions `plan stuck`.
     */
    private function autonomySection(?PlanMode $mode): string
    {
        return match ($mode) {
            null => '',
            PlanMode::Ask => "\n• Autonomy: this project runs plans in ASK mode. Do NOT start implementing yet — present a short "
                . "summary of the plan and ask the user (AskUserQuestion) to confirm before you write any code.",
            PlanMode::Supervised => "\n• Autonomy: grind through the phases on your own, but the user may stop you at any point — "
                . "honour a stop when it comes. Finish with `plan done` once the end gate is clean.",
            PlanMode::Autonomous => "\n• Autonomy: grind through every phase without stopping — the Stop hook keeps you going until you "
                . "run `plan done` (only when the plan is COMPLETE). If you get genuinely blocked and need the user, run `plan stuck` "
                . "instead — it pauses the nudges without ending the plan.",
            PlanMode::Relentless => "\n• Autonomy: RELENTLESS mode — do NOT stop until the plan is COMPLETE, for ANY reason. Do NOT ask "
                . "the user questions and do NOT wait: when you hit a decision, choose the best option yourself and proceed. If a "
                . "phase is genuinely blocked or not worth it, SKIP it — record why in your working notes and move on to the "
                . "remaining phases. There is no pausing for the user in this mode — no blocking, no waiting; the only way the run "
                . "ends is `plan done`, once every reachable phase is finished and the end gate is clean.",
        };
    }

    /**
     * The working-state bullet for the approval nudge — shown only when the project opted into
     * {@see PlanProfile::tracksWorkingState}. It tells the agent to keep the living record current after
     * each phase AND each important event, so a context compaction never loses the conversational deltas.
     */
    private function workingStateSection(PlanProfile $plan): string
    {
        if (! $plan->tracksWorkingState()) {
            return '';
        }

        return "\n• Working state: keep a living record at `.commandments/.plan-working-state`, refreshed after "
            . "each phase AND after each important event (a decision, a plan change we agree in conversation). "
            . "Capture ONLY what `git log` + the plan can't reconstruct — a Done / Doing / Next cursor, the "
            . "decisions you made (and the alternative you rejected + why), plan changes agreed in chat, gotchas, "
            . "and the exact next step. It's re-injected if context compacts, so it's how you survive the loss.";
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

    private function keepGoingNudge(PlanMode $mode): string
    {
        if ($mode->neverStops()) {
            return "Code Commandments — RELENTLESS mode: the plan isn't finished, so do NOT stop. Keep going through the "
                . "remaining phases, commit each, and make your OWN decisions — do not ask the user, do not wait. If a phase "
                . "is genuinely blocked or not worth doing, SKIP it: note why in your working state and move straight on to the "
                . "next phase. You cannot pause for the user in this mode; the run ends only when you run "
                . "`vendor/bin/commandments plan done`, and you may only `done` once every reachable phase is finished and "
                . "`vendor/bin/commandments checks complete` is clean.";
        }

        return "Code Commandments — the plan isn't finished. Keep going: work the remaining phases, commit each, "
            . "and only stop if you genuinely need user input. When every phase is done and "
            . "`vendor/bin/commandments checks complete` is clean, run `vendor/bin/commandments plan done` to finish. "
            . "If you are genuinely BLOCKED and need the user, run `vendor/bin/commandments plan stuck` (NOT `plan done` "
            . "— you may only `done` a COMPLETE plan): it pauses these nudges while keeping the plan active, and tell "
            . "the user what you're blocked on. Nudging resumes on your own once you make progress.";
    }
}
