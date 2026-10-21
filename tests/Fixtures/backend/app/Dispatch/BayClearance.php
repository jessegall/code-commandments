<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\UnnamedCondition;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Walks a round of crates and keeps the reason each one is held back. Three objects are asked in the
 * condition, each about something else, and what the three together MEAN has no name anywhere: the
 * clearance is the thing to name, and a reader is left to assemble it from an or-chain instead.
 */
final class BayClearance
{
    /**
     * @var array<string, string>
     */
    private array $reasons = [];

    public function __construct(private readonly bool $sweeping) {}

    /**
     * @param list<Crate> $round
     */
    #[Sinful(UnnamedCondition::class)]
    public function walk(array $round, LoadingBay $bay): void
    {
        foreach ($round as $crate) {
            if (! $crate->isPacked() || $bay->isBlocked() || $this->sweeping) {
                $this->reasons[$crate->reference] = 'bay '.$bay->number;
            }
        }
    }

    public function summary(): string
    {
        return implode(', ', array_map(
            static fn (string $reference, string $where): string => $reference.' held at '.$where,
            array_keys($this->reasons),
            array_values($this->reasons),
        ));
    }
}
