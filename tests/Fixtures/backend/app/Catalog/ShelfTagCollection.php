<?php

namespace Shop\Catalog;

use Illuminate\Support\Collection;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualHydrationLoop;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\ShelfTagData;

final class ShelfTagCollection
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, ShelfTagData>
     */
    #[Sinful(ManualHydrationLoop::class)]
    public function tags(Collection $rows): Collection
    {
        return $rows->map(fn (array $row) => ShelfTagData::from($row));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    #[Fixed(ManualHydrationLoop::class)]
    public function collected(Collection $rows): Collection
    {
        return ShelfTagData::collect($rows);
    }

    #[Righteous(ManualHydrationLoop::class)]
    public function labels(ShelfLabels $labels): array
    {
        return $labels->map(fn (array $row) => ShelfTagData::from($row));
    }
}

final class ShelfLabels
{
    /**
     * @param  callable(array<string, mixed>): ShelfTagData  $each
     * @return list<ShelfTagData>
     */
    public function map(callable $each): array
    {
        return [$each(['id' => 'A1'])];
    }
}
