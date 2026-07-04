<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

use JesseGall\CodeCommandments\Cli\Hints\Hints;

/**
 * The one entry point behind `bin/commandments`. It parses `$argv` into an {@see Input} exactly
 * once, then dispatches to the {@see Command} registered for the verb — the strategy table that
 * replaced the old `match ($command)`. Registering a command IS wiring it; adding one never edits a
 * switch. `bin/commandments` does nothing but bootstrap (memory + autoload) and call {@see run}.
 */
final class Kernel
{
    /** @var array<string, Command>  verb => handler */
    private array $registry = [];

    public function __construct()
    {
        foreach ($this->commands() as $command) {
            foreach ($command->names() as $name) {
                $this->registry[$name] = $command;
            }
        }
    }

    public function run(array $argv): int
    {
        $input = Input::fromArgv($argv);

        if ($this->wantsHelp($input)) {
            fwrite(STDOUT, self::USAGE);

            return 0;
        }

        $command = $input->command() === '' ? 'judge' : $input->command();
        $handler = $this->registry[$command] ?? null;

        if ($handler === null) {
            fwrite(STDERR, "Unknown command '{$command}'. Try: commandments --help\n");

            return 2;
        }

        return $handler->run($input);
    }

    private function wantsHelp(Input $input): bool
    {
        return in_array($input->command(), ['-h', '--help', 'help'], true)
            || $input->hasFlag('help')
            || in_array('-h', $input->raw(), true);
    }

    /**
     * The registered commands — the whole verb surface. This list is the wiring.
     *
     * @return list<Command>
     */
    private function commands(): array
    {
        return [
            new Judge(),
            new Checks(),
            new Hints(),
            new Repent(),
            new Scaffold(),
            new Report(),
            new FeatureRequest(),
            new Sync(),
            new Install(),
            new HookCommand(['remind'], new Remind()),
            new HookCommand(['judge-reminder'], new JudgeReminder()),
            new HookCommand(['plan-reminder'], new PlanReminder()),
            new PlanCommand(),
            new HookRunner(),
            new Configure(),
            new ConfigCommand(),
            new Exemptions(),
        ];
    }

    private const string USAGE = <<<TXT
        Code Commandments — a compiler for architecture.

        Usage:
          commandments judge [path] [--skill=NAME] [--sin=NAME]   # no [path] → the source roots declared in .commandments/config.php
          commandments judge --list
          commandments checks [start|phase|complete] [--list]  # run the planExecution checks for a plan moment (complete appends judge --branch)
          commandments plan [done|status]  # end the active plan (clears the keep-going nudge) / show it
          commandments hints [path] [--changes|--branch[=BASE]] [--dry-run[=FILE]]  # fix Spatie Data @method/factory hints (scoped = docblock-only)
          commandments repent [path] [--changes|--branch[=BASE]] [--dry-run[=FILE]] [--only=NAME]  # auto-fix sins: maintenance Scribes (Data hints, arrow-fn returns) + extract-component / SwitchCase
          commandments scaffold [--sin=NAME] [--dry-run]  # generate the reusable helper a sin's fix uses (namespace injected)
          commandments report --reason="…" [--detector=NAME] [--file=PATH] [--line=N]  # report a false positive (with --detector) or a global bug
          commandments feature-request --title="…" --reason="…"  # propose a new/changed rule
          commandments disable <sin>  # turn a rule off in .commandments/config.php
          commandments enable <sin>   # turn it back on
          commandments config reindex  # re-detect the source roots and rewrite config.php's paths()
          commandments exemptions [<sin|detector>]  # list exemptions (all, or one detector's)
          commandments install  # wire composer + the Claude Code hooks (reminder + judge nudge), then sync
          commandments sync     # publish skills + refresh the CLAUDE.md briefing
          commandments remind   # emit the cardinal rule as a PostToolUse payload
          commandments judge-reminder  # emit the "did you judge?" nudge (Stop, or PreToolUse on git commit)
          commandments plan-reminder   # open a plan (PostToolUse/ExitPlanMode) + keep it going (Stop)
          commandments hook <Class>    # run a wired hook class (built-in or a consumer's \$config->hook(...))

        Options:
          --skill=NAME       only run detectors for one skill (group), e.g. spatie-data
          --sin=NAME         only run detectors for one sin (lenient name match), e.g. nullable-callback
          --exclude=A,B      skip findings in paths containing any fragment
          --changes          only report sins in files changed in the working tree (alias: --git)
          --branch[=BASE]    only report sins new/changed on this branch vs BASE (default: main)
          --parallel=N       run detectors across N worker processes (default: 8, capped at cores; 1 = off)
          --memory=LIMIT     memory ceiling for the run (default: 2G; -1 for no limit)
          --ignore-package-requirements  keep package-gated rules even if this project lacks the package (cross-project calibration)
          --checklist=FILE   write the checklist here (default: .commandments/sins.md)
          --no-checklist     print only, don't write the checklist file
          --list             list every detector grouped by skill

        With no [path], judge scans the source roots declared by \$config->paths(...)
        in .commandments/config.php — auto-detected on first run from your
        composer.json PSR-4 map (plus app/src), so scaffolding like database/,
        storage/ and config/ isn't judged. Edit that call (or run `commandments
        config reindex` to re-detect) to tune what's in scope; pass an explicit
        [path] to scan it directly instead.

        By default judge writes a Markdown checklist (.commandments/sins.md). Judge
        ONCE, then work that file line-by-line — a full scan is slow — deleting
        each line as you fix its sin. Re-run judge at the end to confirm.

        Files marked @code-commandments-generated are skipped automatically
        (they are regenerated, not hand-authored). Exit code 1 when sins found.


        TXT;
}
