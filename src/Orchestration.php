<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

use JesseGall\CodeCommandments\Cli\Orchestration\Ruling;
use JesseGall\CodeCommandments\Cli\Orchestration\Trap;

/**
 * The fluent BUILDER for a project's orchestration — the branch several workers converge on, who alone may
 * merge into it, and what everyone is told; {@see build} freezes it into the read-only
 * {@see OrchestrationProfile}. Nothing here is required: a project declaring none of it still gets the
 * board, the receipts and the report of who is waiting, and this only adds what cannot be known unless
 * somebody says it.
 */
final class Orchestration
{
    private string $branch = '';

    private string $writer = '';

    /**
     * @var list<Trap>
     */
    private array $traps = [];

    /**
     * @var list<Ruling>
     */
    private array $rulings = [];

    private int $running = 3;

    private int $prefer = 2;

    /**
     * The branch the work converges on. Naming it is what lets a merge into it be judged; without it the
     * package cannot tell a shared branch from any other.
     */
    public function branch(string $branch): self
    {
        $this->branch = $branch;

        return $this;
    }

    /**
     * The one role that may merge into that branch. Everyone else is refused — which is the constraint
     * that most often holds only because somebody remembers it.
     */
    public function writtenBy(string $role): self
    {
        $this->writer = $role;

        return $this;
    }

    /**
     * Something about the world that catches anyone who does not know it. Impersonal, and it never
     * expires, so it belongs in every brief exactly as written.
     */
    public function trap(string $said): self
    {
        $this->traps[] = new Trap($said);

        return $this;
    }

    /**
     * A decision, with the REASON it was made — the half that lets a different reader notice the premise
     * has stopped holding. Recorded so it is stated once and travels; never applied.
     */
    public function ruling(string $decided, string $because, ?string $on = null): self
    {
        $this->rulings[] = new Ruling($decided, $because, $on);

        return $this;
    }

    /**
     * How many workers may be RUNNING at once, and how many is comfortable. The count is of work being
     * done: a worker waiting on the orchestrator holds no slot, since charging it one would bill the
     * user for the tool's own queue.
     */
    public function workers(int $most, int $prefer): self
    {
        $this->running = $most;
        $this->prefer = $prefer;

        return $this;
    }

    public function build(): OrchestrationProfile
    {
        return new OrchestrationProfile(
            $this->branch,
            $this->writer,
            $this->traps,
            $this->rulings,
            $this->running,
            $this->prefer,
        );
    }
}
