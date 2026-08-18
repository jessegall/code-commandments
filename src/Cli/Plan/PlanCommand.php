<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Cli\Judge\Checklist;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\StopCondition\StopConditionGate;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Workspace;

/**
 * `commandments plan <done|status>` — the human/agent handle on the active-plan {@see PlanMarker}
 * that the keep-going Stop hook reads. `done` ends a plan: it clears the marker so the Stop hook
 * stops nudging (the `executing-plans` skill runs this once the end gate is clean). `status` reports
 * whether a plan is active and the resolved {@see \JesseGall\CodeCommandments\PlanExecution} profile.
 * Scoped to the current worktree, like the hook.
 */
final class PlanCommand implements Command
{
    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function names(): array
    {
        return ['plan'];
    }

    public function help(): Help
    {
        return Help::of('The handle on the ACTIVE PLAN marker the keep-going Stop hook reads — scoped to this worktree.')
            ->form('plan status', 'is a plan active (and stuck)? the resolved profile and mode (the default)')
            ->form('plan done', 'end the plan — clears the marker so the keep-going nudge stops (run it once the end gate is clean)')
            ->form('plan stuck', 'signal you are BLOCKED and need the user — pauses the nudge but keeps the plan active')
            ->note('You may only `done` a COMPLETE plan, never a blocked one. The stuck signal holds only at the '
                . 'current HEAD and clears itself once HEAD moves (progress). In the never-stop modes (BestEffort, Relentless) `stuck` REFUSES — there is no waiting: skip the blocker and keep going.');
    }

    public function run(Input $input): int
    {
        $root = $this->io->projectRoot();
        $marker = PlanMarker::inSession(Workspace::at($root));

        return match ($input->firstArgument()->unwrapOr('status')) {
            'done', 'finish', 'complete' => $this->done($marker, $root),
            'stuck', 'blocked' => $this->stuck($marker, $root),
            'status' => $this->status($marker, $root),
            default => $this->usage(),
        };
    }

    /**
     * Signal that the plan is BLOCKED — the agent needs the human and cannot finish. Unlike `done`
     * this keeps the plan ACTIVE; it only stops the keep-going Stop nudge (so a stuck agent isn't
     * looped) until progress is made. A plan that isn't blocked but complete uses `done` instead.
     */
    private function stuck(PlanMarker $marker, string $root): int
    {
        if (! $marker->isActive()) {
            fwrite(STDOUT, "No active plan — nothing to mark stuck.\n");

            return 0;
        }

        // The never-stop modes have no waiting: a blocker is skipped, not paused on. Refuse to mark stuck so
        // the agent can't quietly stall the run — tell it to keep going instead (defer-and-retry in
        // BestEffort, skip-and-move-on in Relentless).
        $mode = Config::load($root)->planExecutionSettings()->mode();

        if ($mode?->defersBlockers()) {
            fwrite(STDOUT,
                "◼ This project runs plans in BEST-EFFORT mode — there is no stopping. Don't wait on a blocker:\n"
                . "  SKIP it, record it as DEFERRED in your working state (with what's blocking it), and keep going.\n"
                . "  Come BACK and retry every deferred step at the END, then `commandments plan done` once the gate is clean.\n");

            return 0;
        }

        if ($mode?->neverStops()) {
            fwrite(STDOUT,
                "◼ This project runs plans in RELENTLESS mode — there is no stopping. Don't wait on a blocker:\n"
                . "  SKIP the blocked phase, note why in your working state, and continue with the remaining phases.\n"
                . "  Run `commandments plan done` only once every reachable phase is finished and the end gate is clean.\n");

            return 0;
        }

        $marker->markStuck($this->io->git()->head($root));
        fwrite(STDOUT,
            "◼ Plan marked STUCK — the keep-going nudge is paused for this stop so you aren't looped while\n"
            . "  blocked; the plan stays active. Tell the user what you're blocked on and stop. As soon as you\n"
            . "  continue, keep-going resumes on its own. (Run `commandments plan done` only if the plan is\n"
            . "  actually complete.)\n");

        return 0;
    }

    private function done(PlanMarker $marker, string $root): int
    {
        if (! $marker->isActive()) {
            fwrite(STDOUT, "No active plan — nothing to finish.\n");

            return 0;
        }

        $constraints = PlanConstraints::inSession(Workspace::at($root), Config::load($root)->planExecutionSettings());

        if ($constraints->active() !== [] && ! $constraints->isVerifiedAt($this->io->git()->head($root))) {
            fwrite(STDERR,
                "✗ Constraints not verified. Before finishing, run `commandments constraints check`, review the\n"
                . "  whole branch diff against each constraint, fix any violation at its source, then run\n"
                . "  `commandments constraints verified` — then `plan done` again.\n");

            return 2;
        }

        $marker->clear();
        $constraints->clear();
        PlanTesting::inSession(Workspace::at($root), Config::load($root)->planExecutionSettings())->clear();
        PlanWorkingState::inSession(Workspace::at($root))->clear();
        Checklist::inSession(Workspace::at($root))->clearAll(); // the plan is over — drop its worklist so no stale
        // reference from an older judge run outlives the plan; the next scan regenerates it.
        fwrite(STDOUT, "✓ Plan marked done — the keep-going Stop nudge is cleared.\n");
        $this->handOverToTheGate(StopConditionGate::inSession(Workspace::at($root))->all());

        return 0;
    }

    /**
     * The handover: a plan holds the stop while it runs, so anything parked with `stop-condition` waited for
     * this moment. Name those conditions here — they take over the next stop, and the agent should not
     * meet them for the first time as a block it forgot about.
     *
     * @param  list<string>  $conditions
     */
    private function handOverToTheGate(array $conditions): void
    {
        if ($conditions === []) {
            return;
        }

        fwrite(STDOUT, '● ' . count($conditions) . " stop condition(s) now hold you — the work parked during the plan:\n");

        foreach ($conditions as $index => $condition) {
            fwrite(STDOUT, '  ' . ($index + 1) . ". {$condition}\n");
        }

        fwrite(STDOUT, "  Do them now, then `commandments stop-condition met <n>` for each once you have VERIFIED it.\n");
    }

    private function status(PlanMarker $marker, string $root): int
    {
        $plan = Config::load($root)->planExecutionSettings();
        $mode = $plan->mode()?->name ?? 'unmanaged';

        fwrite(STDOUT, match (true) {
            ! $marker->isActive() => "○ No plan active.\n",
            $marker->stuckAt() !== null => "◼ Plan active (STUCK).\n",
            default => "● Plan active.\n",
        });
        fwrite(STDOUT, "  branch prefix: `{$plan->prefix()}`  base: `{$plan->baseBranch()}`  mode: {$mode}\n");

        $constraints = PlanConstraints::inSession(Workspace::at($root), $plan);
        $active = $constraints->active();

        if ($active !== []) {
            $verified = $constraints->isVerifiedAt($this->io->git()->head($root)) ? 'verified' : 'not verified';
            fwrite(STDOUT, '  constraints: ' . count($active) . " active ({$verified})\n");
        }

        $method = PlanTesting::inSession(Workspace::at($root), $plan)->effective();

        if ($method !== '') {
            fwrite(STDOUT, "  testing: {$method}\n");
        }

        return 0;
    }

    private function usage(): int
    {
        return HelpScreen::usage($this);
    }
}
