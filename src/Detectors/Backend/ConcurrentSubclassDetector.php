<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ConcurrentSubclass;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A class that `extends Concurrent`. Shared state should be a plain domain object
 * handed out thread-safe by a `::for($id): Concurrent<self>` factory — composition,
 * not inheritance. Subclassing drags the proxy's API onto the domain class (method
 * collisions, no plain unit test). Points at concurrent-state.
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
