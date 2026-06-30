<?php

namespace Shop\Data;

use Spatie\LaravelData\Data;

/**
 * A PLAIN Data class — only scalar props, no cast, map, nested Data, or `fromX()`
 * factory. `::from()` and `new` are equivalent here, so constructing it with `new`
 * is honest. Used by the NewDataObject righteous twin.
 */
final class ShelfTagData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
    ) {}
}
