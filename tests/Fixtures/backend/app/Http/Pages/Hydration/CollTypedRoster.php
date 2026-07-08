<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataCollectionType;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/*
 * Scenario 1 — a season roster typed `DataCollection`. Should be `array` + `#[DataCollectionOf]`.
 */
#[Sinful(DataCollectionType::class)]
final class RosterPage extends Data
{
    /** @var DataCollection<int, Member> */
    public function __construct(
        public readonly string $club,
        public readonly int $season,
        public readonly string $coach,
        public readonly DataCollection $members,
    ) {}

    public function title(): string
    {
        return "{$this->club} {$this->season}";
    }

    public function underCoach(string $name): bool
    {
        return strcasecmp($this->coach, $name) === 0;
    }
}

final class Member extends Data
{
    public function __construct(public readonly string $name) {}
}
