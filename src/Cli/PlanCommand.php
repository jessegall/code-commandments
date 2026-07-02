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
final class PlanCommand
{
    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function run(array $args): int
    {
        $root = $this->io->projectRoot();
        $marker = PlanMarker::inWorktree($root);

        return match ($args[0] ?? 'status') {
            'done', 'finish', 'complete' => $this->done($marker),
            'status' => $this->status($marker, $root),
            default => $this->usage(),
        };
    }

    private function done(PlanMarker $marker): int
    {
        if (! $marker->isActive()) {
            fwrite(STDOUT, "No active plan — nothing to finish.\n");

            return 0;
        }

        $marker->clear();
        fwrite(STDOUT, "✓ Plan marked done — the keep-going Stop nudge is cleared.\n");

        return 0;
    }

    private function status(PlanMarker $marker, string $root): int
    {
        $plan = Config::load($root)->planExecutionSettings();
        $keepGoing = $plan->stopPolicy()?->name ?? 'off';

        fwrite(STDOUT, $marker->isActive() ? "● Plan active.\n" : "○ No plan active.\n");
        fwrite(STDOUT, "  branch prefix: `{$plan->prefix()}`  base: `{$plan->baseBranch()}`  keep-going: {$keepGoing}\n");

        return 0;
    }

    private function usage(): int
    {
        fwrite(STDERR, "Usage: commandments plan <done|status>\n");

        return 2;
    }
}
