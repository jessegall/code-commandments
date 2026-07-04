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
        private ?StopPolicy $stopPolicy,
        private array $constraints,
        private bool $enforceEachPhase,
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

    public function pushesEachPhase(): bool
    {
        return $this->pushEachPhase;
    }

    public function stopPolicy(): ?StopPolicy
    {
        return $this->stopPolicy;
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
}
