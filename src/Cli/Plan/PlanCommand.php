<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Cli\Judge\Checklist;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Input;
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

    public function run(Input $input): int
    {
        $root = $this->io->projectRoot();
        $marker = PlanMarker::inWorktree($root);

        return match ($input->firstArgument('status')) {
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

        $constraints = PlanConstraints::inWorktree($root, Config::load($root)->planExecutionSettings());

        if ($constraints->active() !== [] && ! $constraints->isVerifiedAt($this->io->git()->head($root))) {
            fwrite(STDERR,
                "✗ Constraints not verified. Before finishing, run `commandments constraints check`, review the\n"
                . "  whole branch diff against each constraint, fix any violation at its source, then run\n"
                . "  `commandments constraints verified` — then `plan done` again.\n");

            return 2;
        }

        $marker->clear();
        $constraints->clear();
        PlanTesting::inWorktree($root, Config::load($root)->planExecutionSettings())->clear();
        Checklist::inProject($root)->clearAll(); // the plan is over — drop its worklist so no stale
        // reference from an older judge run outlives the plan; the next scan regenerates it.
        fwrite(STDOUT, "✓ Plan marked done — the keep-going Stop nudge is cleared.\n");

        return 0;
    }

    private function status(PlanMarker $marker, string $root): int
    {
        $plan = Config::load($root)->planExecutionSettings();
        $keepGoing = $plan->stopPolicy()?->name ?? 'off';

        $stuck = $marker->isActive() && $marker->stuckAt() !== null;
        fwrite(STDOUT, $marker->isActive() ? ($stuck ? "◼ Plan active (STUCK).\n" : "● Plan active.\n") : "○ No plan active.\n");
        fwrite(STDOUT, "  branch prefix: `{$plan->prefix()}`  base: `{$plan->baseBranch()}`  keep-going: {$keepGoing}\n");

        $constraints = PlanConstraints::inWorktree($root, $plan);
        $active = $constraints->active();

        if ($active !== []) {
            $verified = $constraints->isVerifiedAt($this->io->git()->head($root)) ? 'verified' : 'not verified';
            fwrite(STDOUT, '  constraints: ' . count($active) . " active ({$verified})\n");
        }

        $method = PlanTesting::inWorktree($root, $plan)->effective();

        if ($method !== '') {
            fwrite(STDOUT, "  testing: {$method}\n");
        }

        return 0;
    }

    private function usage(): int
    {
        fwrite(STDERR, "Usage: commandments plan <done|stuck|status>\n");

        return 2;
    }
}
