<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Config;

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
            'status' => $this->status($marker, $root),
            default => $this->usage(),
        };
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
        Checklist::inProject($root)->clearAll(); // the plan is over — drop its worklist so no stale
        // reference from an older judge run outlives the plan; the next scan regenerates it.
        fwrite(STDOUT, "✓ Plan marked done — the keep-going Stop nudge is cleared.\n");

        return 0;
    }

    private function status(PlanMarker $marker, string $root): int
    {
        $plan = Config::load($root)->planExecutionSettings();
        $keepGoing = $plan->stopPolicy()?->name ?? 'off';

        fwrite(STDOUT, $marker->isActive() ? "● Plan active.\n" : "○ No plan active.\n");
        fwrite(STDOUT, "  branch prefix: `{$plan->prefix()}`  base: `{$plan->baseBranch()}`  keep-going: {$keepGoing}\n");

        $constraints = PlanConstraints::inWorktree($root, $plan);
        $active = $constraints->active();

        if ($active !== []) {
            $verified = $constraints->isVerifiedAt($this->io->git()->head($root)) ? 'verified' : 'not verified';
            fwrite(STDOUT, '  constraints: ' . count($active) . " active ({$verified})\n");
        }

        return 0;
    }

    private function usage(): int
    {
        fwrite(STDERR, "Usage: commandments plan <done|status>\n");

        return 2;
    }
}
