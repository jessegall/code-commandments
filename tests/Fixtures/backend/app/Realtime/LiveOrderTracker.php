<?php

namespace Shop\Realtime;

use JesseGall\CodeCommandments\Sins\Backend\Concurrent\ConcurrentSubclass;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use JesseGall\Concurrent\Concurrent;

/**
 * Live order stage the frontend polls — but welded to the proxy by subclassing
 * instead of composing a Concurrent<self> behind ::for().
 */
#[Sinful(ConcurrentSubclass::class)]
final class LiveOrderTracker extends Concurrent
{
    public string $stage = 'received';

    public function advance(string $stage): void
    {
        $this->stage = $stage;
    }
}

/**
 * The clean twin: a plain domain object handed out thread-safe by a `::for()`
 * factory that wraps it in a `Concurrent<self>` — composition, not inheritance.
 */
#[Righteous(ConcurrentSubclass::class)]
final class LiveOrderStage
{
    public string $stage = 'received';

    public static function for(string $id): Concurrent
    {
        return Concurrent::for(new self());
    }

    public function advance(string $stage): void
    {
        $this->stage = $stage;
    }
}
