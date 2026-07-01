<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ConcurrentSubclass;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A class that `extends` the `jessegall/concurrent` package's `Concurrent` proxy —
 * inheriting the thread-safe shared-state wrapper instead of composing it. Shared
 * state should stay a plain domain object, handed out thread-safe by a
 * `::for($id): Concurrent<self>` factory (composition, not inheritance); subclassing
 * the proxy drags its whole locking/hydration API onto the domain class — method
 * collisions, and no way to unit-test the class in isolation. Points at
 * concurrent-state. Inert unless the project actually uses `jessegall/concurrent`:
 * with no `Concurrent` base class in the codebase, nothing extends it and it never
 * fires.
 */
final class ConcurrentSubclassDetector implements Detector
{
    private const string CONCURRENT = 'JesseGall\\Concurrent\\Concurrent';

    public function sin(): Sin
    {
        return new ConcurrentSubclass();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClassExtending(self::CONCURRENT)
            ->get();
    }
}
