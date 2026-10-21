<?php

namespace Shop\Dispatch;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\HandedOutCollection;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * What each run carried, tallied as it is reported. The tally hands its own collection out, which is the sin; the
 * two methods below it are the righteous twins — an array is COPIED on the way out, so nothing is shared, and a
 * collection asked for a copy of itself is not the thing itself either.
 */
final class RunTally
{
    private Collection $carried;

    private int $crates = 0;

    public function __construct()
    {
        $this->carried = collect();
    }

    public function report(string $run, int $crates): void
    {
        $this->carried->put($run, $crates);
        $this->crates += $crates;
    }

    public function busiest(): string
    {
        return (string) $this->carried->sortDesc()->keys()->first();
    }

    public function total(): int
    {
        return $this->crates;
    }

    #[Sinful(HandedOutCollection::class)]
    public function carried(): Collection
    {
        return $this->carried;
    }

    /**
     * @return array<string, int>
     */
    #[Righteous(HandedOutCollection::class)]
    public function asArray(): array
    {
        return $this->carried->all();
    }

    #[Righteous(HandedOutCollection::class)]
    public function copy(): Collection
    {
        return $this->carried->collect();
    }
}
