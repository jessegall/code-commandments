<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Concurrent;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Concurrent\ConcurrentNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Concurrent\ConcurrentSubclass;
use JesseGall\CodeCommandments\Sins\Sin;

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
    public function sin(): Sin
    {
        return new ConcurrentSubclass();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (ConcurrentNode $node): bool => $node->extendsConcurrent())
            ->get();
    }
}
