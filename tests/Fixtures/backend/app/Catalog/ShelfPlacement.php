<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\CancelledCoalesce;

use JesseGall\CodeCommandments\Testing\Righteous;
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

    #[Sinful(CancelledCoalesce::class)]
    public function isBehindFront(?int $position): bool
    {
        return ($position ?? 0) > 0;
    }

    #[Righteous(CancelledCoalesce::class)]
    public function reachesBackShelf(?int $position): bool
    {
        return ($position ?? 0) > 4;
    }
}
