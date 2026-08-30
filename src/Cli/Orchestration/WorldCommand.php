<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Command;
use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Help\Help;
use JesseGall\CodeCommandments\Cli\Help\HelpScreen;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Hooks\HookIO;
use JesseGall\CodeCommandments\Workspace;

/**
 * Stands up the isolated world one agent runs in. Any agent — the scheduler, a reviewer, a worker sent
 * to a lane — gets the same thing: a directory of settings, and no project instructions, skills or hooks
 * it was not marked for.
 */
final class WorldCommand implements Command
{
    public function __construct(
        private readonly HookIO $io = new HookIO,
        private readonly Console $console = new Console,
    ) {}

    public function names(): array
    {
        return ['world'];
    }

    public function help(): Help
    {
        return Help::of('Prepare the isolated world an agent runs in — settings, and nothing it was not marked for.')
            ->form('world <agent>', 'prepare a WORKER\'s world: the rules about the code it writes, and nothing about running a build')
            ->form('world <agent> --assistant', 'prepare an ASSISTANT\'s world: the same, plus the journal bookkeeping an agent that keeps a record needs')
            ->option('--assistant', 'this agent persists across dispatches and keeps a record, so it hears the journal hooks too')
            ->note('Hand the path to the agent as CLAUDE_CONFIG_DIR. `Stop` is never wired for any of '
                . 'them: a dispatched agent\'s stop IS its completion, so a hook holding it can only push '
                . 'it to speak again — which has been watched running for an evening. A project cannot '
                . 'configure what is in a world; it is derived from which hooks are marked.')
            ->section(Help::HOOKS);
    }

    public function run(Input $input): int
    {
        $agent = $input->firstArgument()->unwrapOr('');

        if ($agent === '') {
            return HelpScreen::usage($this, 'Name the agent: `commandments world <agent>`.');
        }

        $root = $this->io->projectRoot();
        $workspace = Workspace::ofSession($root);
        $assistant = $input->hasFlag('assistant');

        $world = $assistant
            ? World::forAssistant($workspace, $root, $agent)
            : World::forWorker($workspace, $root, $agent);

        if (! $world->prepare()) {
            return $this->console->refuse("Could not prepare {$world->path()}.");
        }

        return $this->console->say(
            sprintf('▸ %s world ready for `%s`.', $assistant ? 'assistant' : 'worker', $agent),
            '  ' . $world->path(),
            '  Hand it over as CLAUDE_CONFIG_DIR; it inherits nothing else.',
        );
    }
}
