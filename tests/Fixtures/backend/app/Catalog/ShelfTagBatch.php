<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ManualHydrationLoop;

use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Data\ProductData;
use Shop\Data\ShelfTagData;

/**
 * Builds shelf tags from raw rows with `array_map` — the same per-item hydration a
 * `foreach` is, just spelled as a callback. Both the first-class-callable form and
 * the arrow-fn form should be flagged; `ShelfTagData::collect($rows)` is the fix.
 */
// Class-level marker: the arrow-fn form's `from()` has no NAMED enclosing function
// (it lives in an anonymous fn), so it is attributed to the class, not a method.
#[Sinful(ManualHydrationLoop::class)]
final class ShelfTagBatch
{
    /**
     * First-class-callable form: `ShelfTagData::from(...)` IS array_map's callback.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, ShelfTagData>
     */
    public function fromRows(array $rows): array
    {
        return array_map(ShelfTagData::from(...), $rows);
    }

    /**
     * Arrow-fn form: `from()` is called inside the callback array_map runs per item.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, ProductData>
     */
    public function products(array $rows): array
    {
        return array_map(fn (array $row): ProductData => ProductData::from($row), $rows);
    }
}
