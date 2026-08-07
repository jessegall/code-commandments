<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments;

/**
 * The resolved plan-execution profile the package reads — built from a {@see PlanExecution} once its
 * fluent setters have run. Getters only, so it can't be mutated and a fluent chain can't misfire into it.
 */
final readonly class PlanProfile
{
    /**
     * @param  list<string>  $onStart
     * @param  list<string>  $eachPhase
     * @param  list<string>  $onComplete
     * @param  list<string>  $constraints
     */
    public function __construct(
        private array $onStart,
        private array $eachPhase,
        private array $onComplete,
        private string $baseBranch,
        private string $branchPrefix,
        private bool $pushEachPhase,
        private ?PlanMode $mode,
        private array $constraints,
        private bool $enforceEachPhase,
        private string $testFlow = '',
        private bool $trackWorkingState = false,
    ) {}

    /**
     * The commands declared for one {@see Moment} — a new moment is one enum case + one bucket.
     *
     * @return list<string>
     */
    public function checksFor(Moment $moment): array
    {
        return match ($moment) {
            Moment::Start => $this->onStart,
            Moment::Phase => $this->eachPhase,
            Moment::Complete => $this->onComplete,
        };
    }

    public function baseBranch(): string
    {
        return $this->baseBranch;
    }

    public function prefix(): string
    {
        return $this->branchPrefix;
    }

    public function isEachPhasePushed(): bool
    {
        return $this->pushEachPhase;
    }

    /**
     * The plan-execution mode — how autonomously an approved plan is run. Null when unmanaged (no mode
     * configured): no confirm gate and no keep-going nudge.
     */
    public function mode(): ?PlanMode
    {
        return $this->mode;
    }

    /**
     * @return list<string>
     */
    public function constraints(): array
    {
        return $this->constraints;
    }

    public function enforcesConstraintsEachPhase(): bool
    {
        return $this->enforceEachPhase;
    }

    /**
     * The project's default testing methodology for a plan run — '' when none is configured.
     */
    public function testFlow(): string
    {
        return $this->testFlow;
    }

    /**
     * Whether the agent keeps a living working-state record while a plan runs — off by default.
     */
    public function isWorkingStateTracked(): bool
    {
        return $this->trackWorkingState;
    }
}
