<?php

namespace Shop\Lockers;

use Shop\Models\Depot;

/**
 * Gives a class the depot it serves.
 */
trait ServesDepot
{
    protected Depot $depot;
}
