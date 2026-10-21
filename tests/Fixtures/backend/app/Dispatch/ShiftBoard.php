<?php

namespace Shop\Dispatch;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\HandedOutCollection;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The shifts a board shows, with the hour each opens. The board hands out the collection it keeps, so a caller
 * that sorts the shifts for its own display reorders the board itself.
 */
final class ShiftBoard
{
    private Collection $shifts;

    public function __construct(private readonly string $depot)
    {
        $this->shifts = collect();
    }

    public function open(string $shift, int $at): void
    {
        $this->shifts->put($shift, $at);
    }

    #[Sinful(HandedOutCollection::class)]
    public function shifts(): Collection
    {
        return $this->shifts;
    }

    public function heading(): string
    {
        return sprintf('%s — %d shifts', $this->depot, $this->shifts->count());
    }
}
