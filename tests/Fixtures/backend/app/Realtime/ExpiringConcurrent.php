<?php

namespace Shop\Realtime;

use JesseGall\CodeCommandments\Sins\Backend\Concurrent\ConcurrentSubclass;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\Concurrent\Concurrent;

/**
 * A concurrent value of any type that forgets itself after a number of seconds: an extension of the proxy, not a
 * domain object wrapped in it.
 *
 * @template TValue
 *
 * @extends Concurrent<TValue>
 */
#[Righteous(ConcurrentSubclass::class)]
class ExpiringConcurrent extends Concurrent
{
    public int $seconds = 60;
}
