<?php

namespace Shop\Catalog;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualHydrationLoop;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\ShelfTagData;

/**
 * Turns the rows of a supplier's tag feed into shelf tags, one from() per row through the collect() helper.
 */
final class ShelfTagFeed
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return Collection<int, ShelfTagData>
     */
    #[Sinful(ManualHydrationLoop::class)]
    public function tags(array $rows): Collection
    {
        return collect($rows)
            ->filter(static fn (array $row) => $row !== [])
            ->map(fn (array $row) => ShelfTagData::from($row));
    }
}
