<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Concurrent;

use JesseGall\CodeCommandments\Sins\RequiresPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Concurrent\ConcurrentState;

final class ConcurrentSubclass extends Sin implements RequiresPackage
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

    public function requiredPackage(): string
    {
        return 'jessegall/concurrent';
    }
}
