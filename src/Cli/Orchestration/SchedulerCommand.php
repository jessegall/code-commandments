<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;

/**
 * Stands a scheduler's world up and hands back what to dispatch. It does not spawn anything itself — a
 * command that started a process would be a process nobody can see, which is the mistake this whole
 * arrangement was rebuilt to remove. The orchestrator makes the call, in view, with its own agent tool.
 */
final class SchedulerCommand implements Command
{
    public function __construct(
        private readonly HookIO $io = new HookIO,
        private readonly Console $console = new Console,
    ) {}

    public function names(): array
    {
        return ['scheduler'];
    }

    public function help(): Help
    {
        return Help::of('Prepare a scheduler\'s isolated world and print the prompt to dispatch it with.')
            ->form('scheduler', 'prepare the world and print the whole prompt — dispatch it yourself with the Agent tool')
            ->note('A scheduler inherits nothing. Its world is one directory holding settings and no '
                . 'project instructions, no skills, and no hooks it does not need — and never `Stop`, '
                . 'since a scheduler\'s stop is its completion and a hook holding it can only push it to '
                . 'speak again. It runs one command, which prints one brief and strikes that line off as '
                . 'it reads, and repeats until nothing is printed.')
            ->section(Help::HOOKS);
    }

    public function run(Input $input): int
    {
        $root = $this->io->projectRoot();
        $workspace = Workspace::ofSession($root);
        $home = World::forWorker($workspace, $root, Scheduler::NAME);

        if (! $home->prepare()) {
            return $this->console->refuse("Could not prepare {$home->path()}.");
        }

        $waiting = count(Pending::inSession($workspace)->all());
        $brief = new Scheduler($root)->brief();

        return $this->console->say(
            "▸ scheduler world ready at {$home->path()}",
            "  {$waiting} dispatch(es) waiting.",
            '',
            '  Dispatch it YOURSELF with the Agent tool, on a small model, with this as its whole prompt:',
            '',
            $brief,
        );
    }
}
