<?php

namespace Shop\Events;

/**
 * A shelf was filled again: the restock screen that raised it was retired with the old handhelds.
 */
final readonly class ShelfRestocked
{
    public function __construct(public string $shelf) {}
}
