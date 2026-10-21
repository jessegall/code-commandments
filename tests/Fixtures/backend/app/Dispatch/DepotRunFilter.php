<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\TrainWreck;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The righteous twin of the walk through three objects. A fluent filter is as long as the caller
 * cares to make it and crosses ONE type, because every link answers with its own kind; and a walk
 * of two steps is still a neighbour and its neighbour, which is how objects talk.
 */
final readonly class DepotRunFilter
{
    /**
     * @param list<DepotRun> $runs
     */
    public function __construct(private array $runs = []) {}

    public function outOf(Depot $depot): self
    {
        return new self(array_values(array_filter($this->runs, fn (DepotRun $run): bool => $run->depot() === $depot)));
    }

    public function referenced(string $prefix): self
    {
        return new self(array_values(array_filter($this->runs, fn (DepotRun $run): bool => str_starts_with($run->reference, $prefix))));
    }

    #[Righteous(TrainWreck::class)]
    public function firstOut(Depot $depot): string
    {
        return $this->outOf($depot)->referenced('AM')->reference();
    }

    #[Righteous(TrainWreck::class)]
    public function townOf(DepotRun $run): string
    {
        return $run->depot()->town();
    }

    public function reference(): string
    {
        return $this->runs === [] ? 'none' : $this->runs[0]->reference;
    }
}
