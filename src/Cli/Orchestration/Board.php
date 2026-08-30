<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\State\Legend;
use JesseGall\CodeCommandments\Cli\State\State;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;

/**
 * Who is holding what, and where each piece stands. It needs no configuration at all — an item is a
 * string and a holder is a string — so a project can use this alone, with subagents in one checkout, and
 * never declare a branch, a role or a worktree.
 */
final class Board
{
    public function __construct(private readonly StateFile $file) {}

    public static function inSession(Workspace $workspace): self
    {
        return new self(new StateFile($workspace->path('.board'), self::legend()));
    }

    public static function legend(): Legend
    {
        return new Legend(
            'Who is holding which piece of work, and where each stands (`commandments build`). A hold is '
                . 'released by accepting or releasing it — never by a process ending, since a worker\'s '
                . 'process ends every time it reports.',
            [
                'running' => 'how many holds may be WORKING at once before a dispatch is refused',
                'prefer' => 'how many is comfortable — said once when crossed, never enforced',
            ],
            defaults: new State(running: 3, prefer: 2),
            list: 'one `item<TAB>holder<TAB>stage<TAB>since<TAB>round` per line — the claims. `stage` is '
                . 'working, reported, blocked or accepted; only `working` occupies one of the slots.',
            safe: 'every hold is forgotten and the work has to be claimed again',
        );
    }

    /**
     * Every claim on the board, in the order it was made.
     *
     * @return list<Claim>
     */
    public function claims(): array
    {
        $claims = [];

        foreach ($this->file->read()->items() as $line) {
            foreach (Claim::fromLine($line) as $claim) {
                $claims[] = $claim;
            }
        }

        return $claims;
    }

    /**
     * The claim on $item, if anybody holds it.
     *
     * @return Option<Claim>
     */
    public function on(string $item): Option
    {
        foreach ($this->claims() as $claim) {
            if ($claim->item === $item && ! $claim->stage->isSettled()) {
                return Option::some($claim);
            }
        }

        return Option::none();
    }

    /**
     * Take $item for $holder. Absent when somebody already holds it — the caller says who, since a
     * refusal that does not name the holder leaves the reader to go looking.
     *
     * @return Option<Claim>
     */
    public function claim(string $item, string $holder, string $at): Option
    {
        if ($this->on($item)->isSome()) {
            return Option::none();
        }

        $claim = new Claim($item, new Hold($holder, $at), Stage::Working);
        $this->put($claim);

        return Option::some($claim);
    }

    /**
     * Move $item to $stage, doing nothing when nobody holds it.
     */
    public function move(string $item, Stage $stage): void
    {
        foreach ($this->on($item) as $claim) {
            $this->put($claim->at($stage));
        }
    }

    public function rework(string $item): void
    {
        foreach ($this->on($item) as $claim) {
            $this->put($claim->reworked());
        }
    }

    /**
     * Every claim occupying a running slot. Only work being done counts — a reported or blocked item is
     * waiting on the orchestrator, and charging it a slot would bill the user for the tool's own queue.
     *
     * @return list<Claim>
     */
    public function running(): array
    {
        return array_values(array_filter($this->claims(), fn (Claim $claim) => $claim->stage->isRunning()));
    }

    /**
     * Every claim with somebody waiting on a decision — the tier surfaced first, because throughput can
     * wait and a person cannot.
     *
     * @return list<Claim>
     */
    public function awaiting(): array
    {
        return array_values(array_filter($this->claims(), fn (Claim $claim) => $claim->stage->awaitsTheOrchestrator()));
    }

    /**
     * How many may run at once, and how many is comfortable.
     */
    public function limit(): int
    {
        return $this->file->read()->int('running');
    }

    public function preferred(): int
    {
        return $this->file->read()->int('prefer');
    }

    public function exists(): bool
    {
        return $this->file->exists();
    }

    /**
     * End the build outright — every hold forgotten, exactly what the legend already promises is safe.
     * The caller answers for whether anything is still {@see running}; this only does the forgetting.
     */
    public function clear(): void
    {
        $this->file->delete();
    }

    /**
     * The live claim a LANE is working under — the question `upgrade` has to answer before it swaps a
     * worktree's `vendor/` out from under somebody, because a spurious gate failure in a running builder
     * is indistinguishable from a real bug in its work.
     *
     * Matched by NAME, since a lane and a claim share nothing else: `lane open <name>` and `build claim
     * <item> --by=<holder>` are two commands and neither forces the other's word, so BOTH halves are
     * compared. A lane named for the item it holds and a lane named for the worker holding it are equally
     * common, and matching only one would leave a live worker unprotected — where matching both can at
     * worst skip a lane, which is the side to be wrong on.
     *
     * Only WORKING counts. A reported or blocked worker is waiting on the orchestrator and is not running
     * anything a swapped vendor could spoil, which is what makes "run it again when they report" a real
     * instruction rather than a hope.
     *
     * @return Option<Claim>
     */
    public function workingIn(string $lane): Option
    {
        foreach ($this->claims() as $claim) {
            if (! $claim->stage->isRunning()) {
                continue;
            }

            if ($claim->item === $lane || $claim->isHeldBy($lane)) {
                return Option::some($claim);
            }
        }

        return Option::none();
    }

    /**
     * Write $claim, replacing any earlier line for the same item so the board holds one line per item.
     */
    private function put(Claim $claim): void
    {
        $state = $this->file->read();
        $kept = [];

        foreach ($state->items() as $line) {
            $kept[] = Claim::fromLine($line)->isSomeAnd(fn (Claim $was) => $was->item === $claim->item)
                ? $claim->toLine()
                : $line;
        }

        if (! in_array($claim->toLine(), $kept, true)) {
            $kept[] = $claim->toLine();
        }

        $this->file->write($state->withItems($kept));
    }
}
