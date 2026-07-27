<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Moment;
use JesseGall\CodeCommandments\PlanProfile;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
/**
 * Runs PlanProfile checks for a plan moment (`start`, `phase`, `complete`); the `complete` gate
 * appends `judge --branch` to ensure plans finish judged.
 */
final class Checks implements Command
{
    public function names(): array
    {
        return ['checks'];
    }

    public function help(): Help
    {
        return Help::of("Run the project's planExecution() checks for one moment of a plan.")
            ->form('checks [start|phase|complete]', 'run that moment\'s checks in order, stopping at the first failure (default: complete)')
            ->form('checks <moment> --list', 'print the commands that moment would run, without running them')
            ->option('--list', 'print the resolved commands instead of running them')
            ->note('`start` runs once before the first phase, `phase` after each phase, `complete` at the end — and '
                . '`complete` always appends `judge --branch` (plus `constraints check` when the plan declares constraints), so a plan cannot finish unjudged.');
    }

    public function run(Input $input): int
    {
        $moment = Moment::fromToken($input->firstArgument());
        $commands = $this->commands($moment, Config::load()->planExecutionSettings());

        if ($input->hasFlag('list')) {
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
    public function commands(Moment $moment, PlanProfile $plan): array
    {
        $commands = $plan->checksFor($moment);

        if ($moment->appendsJudge()) {
            $commands[] = 'vendor/bin/commandments judge --branch=' . $plan->baseBranch();
        }

        if ($this->appendsConstraintCheck($moment, $plan)) {
            $commands[] = 'vendor/bin/commandments constraints check';
        }

        return $commands;
    }

    /**
     * Should the constraint diff-check trail these checks? Always at `complete` (the hard gate), and at
     * each `phase` only when the project opts in with `enforceConstraintsEachPhase`. Gated on the project
     * declaring constraints, so a project that uses none never sees the line. Local-only constraints are
     * still enforced by the `plan done` gate + the executing-plans skill.
     */
    private function appendsConstraintCheck(Moment $moment, PlanProfile $plan): bool
    {
        if ($plan->constraints() === []) {
            return false;
        }

        return $moment === Moment::Complete
            || ($moment === Moment::Phase && $plan->enforcesConstraintsEachPhase());
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

}
