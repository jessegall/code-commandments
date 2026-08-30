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
 * What a triggered agent still has to read. It is the agent's OWN loop that calls this, not a person: a
 * dispatched agent finishes one subject, asks for the next, and stops when there is none — so a run of
 * commits is read one at a time by one conversation rather than by several fighting over a lane.
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
        return Help::of('What a triggered agent is reading, and what is still waiting for it.')
            ->form('queue status [<agent>]', 'what each triggered agent is working on, and how much is behind it')
            ->form('queue next <agent>', 'finish the current subject and print the brief for the next — prints NOTHING when the queue is empty, which is how the agent knows to stop')
            ->section(Help::HOOKS);
    }

    public function run(Input $input): int
    {
        $workspace = Workspace::ofSession($this->io->projectRoot());

        return match ($input->firstArgument()->unwrapOr('status')) {
            'next' => $this->next($workspace, $input->argument(1)->unwrapOr('')),
            default => $this->status($workspace, $input->argument(1)->unwrapOr('')),
        };
    }

    /**
     * Finish what the agent was reading and hand it the next brief. Printing NOTHING is the signal to
     * stop — a loop that has to parse a sentence to learn it is done is one that eventually misreads it.
     */
    private function next(Workspace $workspace, string $agent): int
    {
        if ($agent === '') {
            return $this->console->refuse('Name the agent: `commandments queue next <agent>`.');
        }

        $queue = Queue::forAgent($workspace, $agent);

        foreach ($queue->finishAndTakeNext() as $subject) {
            foreach (Profiles::inForce($workspace) as $profile) {
                $this->console->write($this->brief($profile, $agent, $subject));

                return 0;
            }
        }

        // Idle: the hold it took when it started is settled, so the orchestrator's board stops showing
        // work that has finished. An item left `working` is the record lying, which is the one thing the
        // board exists to prevent.
        foreach (Profiles::inForce($workspace) as $profile) {
            foreach ($profile->boundTo('commit') as $duty) {
                if ($duty->agent === $agent) {
                    Board::inSession($workspace)->move($duty->procedure, Stage::Reported);
                }
            }
        }

        return 0;
    }

    /**
     * What a continuing agent is told: the new subject and nothing else. It already holds its role and
     * its procedure from the brief that opened the session, so restating them would spend the context
     * that makes a continuing reader worth having.
     */
    private function brief(Profile $profile, string $agent, string $subject): string
    {
        foreach ($profile->boundTo('commit') as $duty) {
            if ($duty->agent === $agent) {
                return "Another commit landed: {$subject}. Carry out the same procedure against it, and "
                    . 'report as before.';
            }
        }

        return "Another subject: {$subject}. Carry out the same procedure against it, and report as before.";
    }

    private function status(Workspace $workspace, string $agent): int
    {
        $said = [];

        foreach (Profiles::inForce($workspace) as $profile) {
            foreach ($profile->allSettings() as $trigger => $ignored) {
                foreach ($profile->boundTo((string) $trigger) as $duty) {
                    if ($agent !== '' && $duty->agent !== $agent) {
                        continue;
                    }

                    $queue = Queue::forAgent($workspace, $duty->agent);
                    $running = $queue->running();

                    $said[] = sprintf(
                        '  %-12s %s%s',
                        $duty->agent,
                        $running === '' ? 'idle' : 'reading ' . substr($running, 0, 7),
                        $queue->waiting() === [] ? '' : sprintf('  (%d waiting)', count($queue->waiting())),
                    );
                }
            }
        }

        return $said === []
            ? $this->console->say('Nothing is triggered. `commandments orchestrate on <trigger> <agent> <procedure>`.')
            : $this->console->say('Triggered agents:', ...$said);
    }
}
