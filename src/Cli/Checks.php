<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Moment;
use JesseGall\CodeCommandments\PlanExecution;

/**
 * `commandments checks [start|phase|complete] [--list]` — run the {@see PlanExecution} checks for
 * one {@see Moment} of a plan. The `executing-plans` skill calls it at each moment: `start` before
 * the first phase, `phase` after each phase, `complete` (the default) at the very end. The
 * `complete` gate always appends `judge --branch`, so a plan can never finish unjudged.
 *
 * Commands run in order via {@see passthru}, streaming their own output, and the gate stops at the
 * first failure with that command's exit code — so the agent sees exactly what broke, fixes it at
 * the source, and re-runs. `--list` prints the resolved commands instead of running them.
 */
final class Checks
{
    public function run(array $args): int
    {
        $moment = Moment::fromToken($this->positional($args));
        $commands = $this->commands($moment, Config::load()->planExecutionSettings());

        if (in_array('--list', $args, true)) {
            foreach ($commands as $command) {
                fwrite(STDOUT, $command . "\n");
            }

            return 0;
        }

        return $this->execute($commands);
    }

    /**
     * The ordered commands for a moment — the declared bucket, plus `judge --branch` when the
     * moment {@see Moment::appendsJudge appends it}. Pure, so the resolution is directly testable.
     *
     * @return list<string>
     */
    public function commands(Moment $moment, PlanExecution $plan): array
    {
        $commands = $plan->checksFor($moment);

        if ($moment->appendsJudge()) {
            $commands[] = 'vendor/bin/commandments judge --branch=' . $plan->baseBranch();
        }

        return $commands;
    }

    /**
     * Run each command in order, streaming output; stop at the first non-zero exit and return it.
     *
     * @param  list<string>  $commands
     */
    private function execute(array $commands): int
    {
        foreach ($commands as $command) {
            fwrite(STDOUT, "\n▶ {$command}\n");
            passthru($command, $exit);

            if ($exit !== 0) {
                fwrite(STDERR, "\n✗ check failed ({$exit}): {$command}\n");

                return $exit;
            }
        }

        return 0;
    }

    /**
     * The first non-flag argument — the moment token, or null (⇒ the default `complete`).
     */
    private function positional(array $args): ?string
    {
        foreach ($args as $arg) {
            if (! str_starts_with((string) $arg, '--')) {
                return (string) $arg;
            }
        }

        return null;
    }
}
