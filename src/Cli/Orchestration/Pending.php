<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Support\Lock;
use JesseGall\CodeCommandments\Workspace;

/**
 * The dispatches a moment has asked for and nobody has made yet — what holds the orchestrator's stop until
 * it has started them itself, in view. One agent's work STACKS rather than replacing: a second moment
 * arriving before the first was acted on is a second piece of work, and dropping it means a commit nobody
 * reviewed, which is invisible.
 */
final readonly class Pending
{
    public function __construct(private StateFile $file) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self(new StateFile($workspace->path('.pending-dispatches'), self::legend()));
    }

    private static function legend(): Legend
    {
        return new Legend(
            'Work a moment asked for that the orchestrator has not dispatched yet. It is what holds a '
                . 'stop: while a line stands here you are asked to start that agent yourself. Deleting '
                . 'the file drops the work — nothing else remembers it.',
            ['held' => 'how many stops this has held, so a loop cannot hold one for ever'],
            defaults: new State(held: 0),
            list: 'one undispatched piece of work per line: when · moment · subject · agent · procedure',
        );
    }

    /**
     * Write one down, unless the same work is already waiting. The read and the write are ONE act: two
     * hooks can fire in the same instant, and a list that is read and then written loses whichever wrote
     * first.
     */
    public function add(Dispatched $work): bool
    {
        $lock = Lock::on($this->file->path() . '.lock');

        if ($lock->isNone()) {
            return false;
        }

        try {
            foreach ($this->all() as $standing) {
                if ($standing->isSameAs($work)) {
                    return false;
                }
            }

            $state = $this->file->read();
            $this->file->write($state->withItems([...$state->items(), $work->toLine()]));

            return true;
        } finally {
            $lock->unwrap()->release();
        }
    }

    /**
     * @return list<Dispatched>
     */
    public function all(): array
    {
        $found = [];

        foreach ($this->file->read()->items() as $line) {
            foreach (Dispatched::fromLine($line) as $work) {
                $found[] = $work;
            }
        }

        return $found;
    }

    /**
     * Strike off whatever $agent was waiting to be given, and say how much that was. The orchestrator
     * says so once it has actually made the call — which is the one thing no tool here can observe.
     */
    public function dispatched(string $agent): int
    {
        $lock = Lock::on($this->file->path() . '.lock');

        if ($lock->isNone()) {
            return 0;
        }

        try {
            $kept = [];
            $struck = 0;

            foreach ($this->all() as $work) {
                if ($agent === '' || $work->agent === $agent) {
                    $struck++;

                    continue;
                }

                $kept[] = $work->toLine();
            }

            $state = $this->file->read();
            $this->file->write($kept === [] ? $state->with(held: 0)->withItems([]) : $state->withItems($kept));

            return $struck;
        } finally {
            $lock->unwrap()->release();
        }
    }

    /**
     * Count this stop as held, and answer with the running total. Kept INSIDE the pending list rather than
     * beside it, so lifting the work lifts the count with it — a count that outlives what it counts is
     * inherited by the next thing and holds a stop nobody asked to hold.
     */
    public function held(): int
    {
        $state = $this->file->read();
        $held = $state->int('held') + 1;

        $this->file->write($state->with(held: $held));

        return $held;
    }

    public function isEmpty(): bool
    {
        return $this->all() === [];
    }
}
