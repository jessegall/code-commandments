<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\CoupledFields;
use JesseGall\CodeCommandments\Testing\Sinful;

final class Bay
{
    public function __construct(public readonly string $aisle, public readonly int $level) {}
}

final class Fixture
{
    public function __construct(public readonly Bay $slot) {}
}

/*
 * Pattern: cross-object same-type peers. `anchor` (a Bay) is combined with `neighbour->slot` (also a Bay)
 * everywhere — two peers of one shelf run, held one directly and one reached through a sibling. They belong
 * together on one object.
 */
#[Sinful(CoupledFields::class)]
final class ShelfPlan
{
    public function __construct(
        public readonly Bay $anchor,
        public readonly Fixture $neighbour,
    ) {}

    public function run(): array
    {
        return [$this->anchor, $this->neighbour->slot];
    }

    public function labelled(): string
    {
        return $this->describe($this->anchor, $this->neighbour->slot);
    }

    private function describe(Bay $from, Bay $to): string
    {
        return "{$from->aisle}-{$to->aisle}";
    }
}
