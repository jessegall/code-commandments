<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

/**
 * Houses the method whose `string $action` parameter Pattern 3 should
 * flag — call sites in `Caller.php` pass literals matching every case
 * of `MirroringAction`, which lives one file over and is not imported
 * here.
 */
class Broadcaster
{
    public function dispatch(string $action): void
    {
        // intentionally empty — the body doesn't matter, the call sites do
    }
}
