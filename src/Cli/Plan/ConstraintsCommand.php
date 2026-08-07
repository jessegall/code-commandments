<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Plan;

use JesseGall\CodeCommandments\Config;

use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Workspace;
/**
 * `commandments constraints <list|add|check|verified>` — the agent's handle on the plan's constraints
 * ({@see PlanConstraints}): list the invariants in force, add a local one, print them with the
 * whole-branch introspection instruction, or stamp them verified (which unblocks the `plan done` gate).
 */
final class ConstraintsCommand implements Command
{
    public function __construct(private readonly HookIO $io = new HookIO) {}

    public function names(): array
    {
        return ['constraints', 'constraint'];
    }

    public function help(): Help
    {
        return Help::of("The plan's architectural invariants — the rules the whole branch must still hold at the end.")
            ->form('constraints list', 'the invariants in force for this plan (the default)')
            ->form('constraints add "<rule>"', 'add one for this plan only, alongside the project\'s declared ones')
            ->form('constraints check', 'print them with the whole-branch introspection instruction')
            ->form('constraints verified', 'stamp them verified — this is what unblocks the `plan done` gate')
            ->note('`checks complete` runs `constraints check` for you whenever the project declares any, so a plan '
                . 'cannot finish with its invariants unexamined.');
    }

    public function run(Input $input): int
    {
        $root = $this->io->projectRoot();
        $plan = Config::load($root)->planExecutionSettings();
        $constraints = PlanConstraints::inSession(Workspace::at($root), $plan);

        return match ($input->firstArgument()->unwrapOr('list')) {
            'list' => $this->list($constraints),
            'add' => $this->add($constraints, $input),
            'check' => $this->check($constraints, $plan->baseBranch()),
            'verified', 'verify', 'ok' => $this->verified($constraints, $root),
            default => $this->usage(),
        };
    }

    private function list(PlanConstraints $constraints): int
    {
        $active = $constraints->active();

        if ($active === []) {
            fwrite(STDOUT, "No constraints in force for this plan.\n");

            return 0;
        }

        fwrite(STDOUT, "Constraints in force for this plan:\n" . $this->numbered($active));

        return 0;
    }

    private function add(PlanConstraints $constraints, Input $input): int
    {
        $rule = trim(implode(' ', array_slice($input->arguments(), 1)));

        if ($rule === '') {
            fwrite(STDERR, "Usage: commandments constraints add \"<rule>\"\n");

            return 2;
        }

        $constraints->addLocal($rule);
        fwrite(STDOUT, "✓ Constraint recorded for this plan: {$rule}\n");

        return 0;
    }

    private function check(PlanConstraints $constraints, string $base): int
    {
        $active = $constraints->active();

        if ($active === []) {
            fwrite(STDOUT, "No constraints to check for this plan.\n");

            return 0;
        }

        fwrite(STDOUT, "Constraints to verify:\n" . $this->numbered($active) . "\n"
            . "Review the ENTIRE branch — everything you created across all phases, not just your last\n"
            . "change: `git diff {$base}...HEAD` (three-dot: the branch's full work since it left `{$base}`).\n"
            . "For EACH constraint above, confirm compliance or fix the violation AT ITS SOURCE and commit.\n"
            . "When every constraint holds, run `commandments constraints verified`.\n");

        return 0;
    }

    private function verified(PlanConstraints $constraints, string $root): int
    {
        $head = $this->io->git()->head($root);

        if ($head === '') {
            fwrite(STDERR, "Can't verify — no commits on this branch yet.\n");

            return 2;
        }

        $constraints->markVerified($head);
        fwrite(STDOUT, '✓ Constraints verified at ' . substr($head, 0, 12) . " — `plan done` is unblocked.\n");

        return 0;
    }

    /**
     * @param  list<string>  $rules
     */
    private function numbered(array $rules): string
    {
        $out = '';

        foreach ($rules as $index => $rule) {
            $out .= '  ' . ($index + 1) . ". {$rule}\n";
        }

        return $out;
    }

    private function usage(): int
    {
        return HelpScreen::usage($this);
    }
}
