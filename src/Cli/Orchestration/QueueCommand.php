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
 * What a moment has asked for and nobody has dispatched yet. A hook writes the work down and holds the
 * orchestrator's stop until it has started the agent ITSELF — so this is the pair of that hold: what is
 * still owed, and the word that says one has been given.
 */
final class QueueCommand implements Command
{
    public function __construct(
        private readonly HookIO $io = new HookIO,
        private readonly Console $console = new Console,
    ) {}

    public function names(): array
    {
        return ['queue'];
    }

    public function help(): Help
    {
        return Help::of('Agents a moment has asked for and nobody has dispatched yet.')
            ->form('queue', 'what is still owed — one line per undispatched agent')
            ->form('queue brief <agent>', 'the WHOLE prompt to hand that agent, as it stands')
            ->form('queue dispatched <agent>', 'say you have made the call, so the stop it was holding is released')
            ->note('Nothing here starts an agent. A hook cannot start one where the person whose machine '
                . 'it runs on can see it, and one started behind them outlives the binding that asked for '
                . 'it — so the orchestrator dispatches it in its own session, where a subagent already '
                . 'shares the board, the plan and the journal.')
            ->section(Help::HOOKS);
    }

    public function run(Input $input): int
    {
        $workspace = Workspace::ofSession($this->io->projectRoot());

        return match ($input->firstArgument()->unwrapOr('status')) {
            'dispatched', 'done' => $this->dispatched($workspace, $input->argument(1)->unwrapOr('')),
            'brief' => $this->brief($workspace, $input->argument(1)->unwrapOr('')),
            default => $this->status($workspace),
        };
    }

    /**
     * Strike off what $agent was owed. Only the orchestrator can say this: whether an Agent call was
     * actually made is the one fact no tool here can observe.
     */
    private function dispatched(Workspace $workspace, string $agent): int
    {
        if ($agent === '') {
            return $this->console->refuse('Name the agent you dispatched: `commandments queue dispatched <agent>`.');
        }

        $struck = Pending::inSession($workspace)->dispatched($agent);

        if ($struck === 0) {
            return $this->console->refuse("Nothing was waiting for `{$agent}`.", '  `commandments queue` — what is.');
        }

        return $this->console->say("Struck off {$struck} for `{$agent}`.");
    }

    private function brief(Workspace $workspace, string $agent): int
    {
        if ($agent === '') {
            return $this->console->refuse('Name the agent: `commandments queue brief <agent>`.');
        }

        $dispatcher = new Dispatcher($workspace, $this->io->projectRoot());
        $said = [];

        foreach (Pending::inSession($workspace)->all() as $work) {
            if ($work->agent === $agent) {
                $said[] = $dispatcher->briefFor($work);
            }
        }

        return $said === []
            ? $this->console->refuse("Nothing is waiting for `{$agent}`.")
            : $this->console->say(...$said);
    }

    private function status(Workspace $workspace): int
    {
        $said = [];

        foreach (Pending::inSession($workspace)->all() as $work) {
            $said[] = '  ' . $work->render();
        }

        return $said === []
            ? $this->console->say('Nothing is waiting to be dispatched.')
            : $this->console->say(
                'Waiting to be dispatched — your stop is held until each has been:',
                ...$said,
                ...['', '  `commandments queue brief <agent>` — the prompt; `queue dispatched <agent>` once you have made the call.'],
            );
    }
}
