<?php

namespace Shop\Catalog;

use Shop\Data\ShelfTagData;

/**
 * Righteous twin for NewDataObject: `new ShelfTagData(...)` constructs a PLAIN Data
 * class (scalars only, no cast/map/nest/factory), so `::from()` would do nothing
 * `new` doesn't. The detector must leave this alone.
 */
final class ShelfTagBuilder
{
    public function build(string $id, string $label): ShelfTagData
    {
        return new ShelfTagData($id, $label);
    }
}
