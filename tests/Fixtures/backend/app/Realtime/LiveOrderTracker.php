<?php

namespace Shop\Realtime;

use JesseGall\CodeCommandments\Detectors\Backend\ConcurrentSubclassDetector;
use JesseGall\CodeCommandments\Testing\Sinful;
use JesseGall\Concurrent\Concurrent;

/**
 * Live order stage the frontend polls — but welded to the proxy by subclassing
 * instead of composing a Concurrent<self> behind ::for().
 */
#[Sinful(ConcurrentSubclassDetector::class)]
final class LiveOrderTracker extends Concurrent
{
    public string $stage = 'received';

    public function advance(string $stage): void
    {
        $this->stage = $stage;
    }
}
