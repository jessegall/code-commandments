<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\ContainerBindings;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\OrphanedBinding;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A `bind`/`singleton`/`scoped`/`instance` registration whose abstract nothing outside the
 * registration ever names — no type-hint, no `app()`/`make()`, no mention at all. The service it
 * points at is gone or unreachable, but the wiring still reads as load-bearing, so it survives every
 * refactor until someone traces consumers by hand. References made INSIDE a registration don't
 * count: a factory closure building the very thing it binds proves nothing about demand. Only an
 * abstract this codebase DECLARES is judged — a vendor contract's consumers live in code the scan
 * never reads. Points at laravel-idioms.
 */
final class OrphanedBindingDetector implements Detector
{
    public function sin(): Sin
    {
        return new OrphanedBinding();
    }

    public function find(Codebase $codebase): array
    {
        $bindings = ContainerBindings::forCodebase($codebase);

        return $codebase
            ->where(static fn (LaravelNode $node): bool => $node->boundAbstract() !== null)
            ->where(static fn (LaravelNode $node): bool => $bindings->isDeclaredHere($node->boundAbstract()))
            ->reject(static fn (LaravelNode $node): bool => $bindings->isResolvedSomewhere($node->boundAbstract()))
            ->get();
    }
}
