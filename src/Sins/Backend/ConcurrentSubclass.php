<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ConcurrentState;

final class ConcurrentSubclass extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'concurrent-subclass',
            skill: ConcurrentState::class,
            description: "Class `extends Concurrent` instead of composing `Concurrent<self>`",
            rule: "Compose `Concurrent<self>` via a `::for()` factory; never `extends Concurrent`.",
            suggestion: "Compose `Concurrent<self>` behind a `::for(\$id)` factory."
        );
    }
}
