<?php

namespace Shop\Fulfillment;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\QueryInLoop;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Models\Depot;

/**
 * Empties a depot that closes, locker by locker.
 */
final class DepotClosing
{
    #[Sinful(QueryInLoop::class)]
    public function close(Depot $depot): void
    {
        foreach ($depot->lockers as $locker) {
            $locker->delete();
        }
    }
}
