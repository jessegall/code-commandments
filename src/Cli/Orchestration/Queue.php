<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * What one agent still has to read. An agent runs ONE thing at a time — it is a conversation, not a pool —
 * so a trigger firing while it works must queue rather than start a second in the same lane. Dropping the
 * work would be worse: a commit nobody reviewed is invisible, and a run of them is when review is worth
 * most.
 */
final readonly class Queue
{
    public function __construct(private StateFile $file) {}

    public static function forAgent(Workspace $workspace, string $agent): self
    {
        return new self(new StateFile($workspace->path('.queue-' . $agent), self::legend($agent)));
    }

    private static function legend(string $agent): Legend
    {
        return new Legend(
            "What `{$agent}` is reading and what is still waiting. Deleting it only means the next trigger "
                . 'starts a fresh run; nothing that was queued is owed once the build is over.',
            ['running' => 'what it is working on now — empty when it is idle'],
            defaults: new State(running: ''),
            list: 'one waiting subject per line, oldest first',
        );
    }

    public function isRunning(): bool
    {
        return $this->file->read()->text('running') !== '';
    }

    /**
     * Take $subject if the agent is free, else queue it. The answer says which happened, because the two
     * are different things to tell a reader: one starts an agent, the other means it will be along.
     */
    public function take(string $subject): bool
    {
        $state = $this->file->read();

        if ($state->text('running') !== '') {
            $this->file->write($state->withItems([...$state->items(), $subject]));

            return false;
        }

        $this->file->write($state->with(running: $subject));

        return true;
    }

    /**
     * Finish what is running and hand back what is next, taking it in the same act — so nothing can slip
     * in between a read and a write and be started twice.
     *
     * @return Option<string>
     */
    public function finishAndTakeNext(): Option
    {
        $state = $this->file->read();
        $waiting = $state->items();

        if ($waiting === []) {
            $this->file->write($state->with(running: '')->withItems([]));

            return Option::none();
        }

        $next = array_shift($waiting);

        $this->file->write($state->with(running: $next)->withItems(array_values($waiting)));

        return Option::some($next);
    }

    /**
     * @return list<string>
     */
    public function waiting(): array
    {
        return $this->file->read()->items();
    }

    public function running(): string
    {
        return $this->file->read()->text('running');
    }
}
