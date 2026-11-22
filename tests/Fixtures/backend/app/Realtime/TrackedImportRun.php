<?php

namespace Shop\Realtime;

use JesseGall\CodeCommandments\Sins\Backend\Concurrent\ConcurrentSubclass;
use JesseGall\CodeCommandments\Testing\Sinful;
use JesseGall\Concurrent\Concurrent;

/**
 * An import run whose progress lives in the shared cache: a domain object that extends the proxy
 * to keep its own fields in it.
 *
 * @template TData of object
 *
 * @extends Concurrent<TData>
 *
 * @property int|null $finishedAt
 */
#[Sinful(ConcurrentSubclass::class)]
final class TrackedImportRun extends Concurrent
{
    public function finish(int $at): void
    {
        $this->finishedAt = $at;
    }
}
