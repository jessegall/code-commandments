<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\CancelledCoalesce;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Where a product sits on the shelf. Position 0 is the FRONT; no position at all means nobody has
 * placed it yet — two different facts the gate below answers with one branch.
 */
final class ShelfPlacement
{
    #[Sinful(CancelledCoalesce::class)]
    public function isAtTheFront(?int $position): bool
    {
        return ($position ?? 0) === 0;
    }
}
