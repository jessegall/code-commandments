<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataCollectionType;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/*
 * Scenario 1 — a season roster typed `DataCollection`. Should be `array` + `#[DataCollectionOf]`.
 */
#[Sinful(DataCollectionType::class)]
final class RosterPage extends Data
{
    /**
     * @var DataCollection<int, Member>
     */
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

/**
 * The FIX for {@see RosterPage}: the roster is typed `array` and declares its element type with
 * `#[DataCollectionOf(Member::class)]`. The element typing drives hydration and nested validation,
 * and the generated TypeScript is a clean `Member[]` instead of `undefined<number, Member>`.
 */
#[Fixed(DataCollectionType::class)]
final class SquadPage extends Data
{
    /**
     * @param list<Member> $members
     */
    public function __construct(
        public readonly string $club,
        public readonly int $season,
        #[DataCollectionOf(Member::class)]
        public readonly array $members = [],
    ) {}

    public function squadSize(): int
    {
        return count($this->members);
    }
}
