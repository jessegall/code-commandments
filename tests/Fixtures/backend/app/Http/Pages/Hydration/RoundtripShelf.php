<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataToArrayRoundtrip;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/*
 * N4 scenario 4 — EACH element arrayed on its way into a collection slot of that same `Data`: `array_map` turns
 * every ready badge into an array the slot then builds back into the badge it was.
 */
final class BadgeShelf extends Data
{
    public function __construct(
        #[DataCollectionOf(BadgeCopy::class)]
        public readonly array $badges,
    ) {}
}

final class BadgeShelfBuilder
{
    /**
     * @param  list<BadgeCopy>  $badges
     */
    #[Sinful(DataToArrayRoundtrip::class)]
    public function shelve(array $badges): BadgeShelf
    {
        return BadgeShelf::from(['badges' => array_map(fn (BadgeCopy $badge) => $badge->toArray(), $badges)]);
    }

    /**
     * The FIX: the slot holds `BadgeCopy` elements, so the list goes in as it is.
     *
     * @param  list<BadgeCopy>  $badges
     */
    #[Fixed(DataToArrayRoundtrip::class)]
    public function shelveReady(array $badges): BadgeShelf
    {
        return BadgeShelf::from(['badges' => $badges]);
    }

    /**
     * @param  list<BadgeCopy>  $badges
     * @return list<array<string, mixed>>
     */
    #[Righteous(DataToArrayRoundtrip::class)]
    public function export(array $badges): array
    {
        // The arrays leave as arrays: nothing builds them back into a badge.
        return array_map(fn (BadgeCopy $badge) => $badge->toArray(), $badges);
    }
}
